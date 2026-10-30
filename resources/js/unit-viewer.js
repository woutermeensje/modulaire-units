import {
  Box3,
  BoxGeometry,
  CanvasTexture,
  CircleGeometry,
  Color,
  CylinderGeometry,
  DirectionalLight,
  ExtrudeGeometry,
  Fog,
  Group,
  HemisphereLight,
  Mesh,
  MeshStandardMaterial,
  NeutralToneMapping,
  Path,
  PCFShadowMap,
  PerspectiveCamera,
  PMREMGenerator,
  RepeatWrapping,
  Scene,
  Shape,
  SphereGeometry,
  SRGBColorSpace,
  Vector3,
  WebGLRenderer,
} from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';

// Dimensions in metres, matching App\Support\UnitRenderer.
const HEIGHT = 2.6;
const CLADDING_THICKNESS = 0.05;
const LINING_THICKNESS = 0.05;
const POST_SIZE = 0.14;
const TOP_RAIL = 0.2;
const BOTTOM_RAIL = 0.15;
const RAISE = 0.1;
const CORNER_CASTING = 0.18;

// Window and door sizes and placement, matching App\Support\UnitConfiguration::OPENING_SIZES and openings().
const OPENING_SIZES = {
  window: { width: 1.8, height: 1.2, bottom: 0.9, slots: 2 },
  door: { width: 0.875, height: 2.125, bottom: 0.15, slots: 1 },
};
const DOOR_CLEAR = { width: 0.811, height: 2.093 };
const POST_CLEARANCE = 0.12;

/**
 * Where the windows and doors of a wall are, in metres from its left end (seen from outside), each centred on its
 * slots but kept clear of the posts at wall ends and at seams between units every `segment` slots.
 */
function openings(slots, segment) {
  const result = [];
  let index = 0;

  while (index < slots.length) {
    const size = OPENING_SIZES[slots[index]];
    if (!size) {
      index++;
      continue;
    }

    const start = Math.floor(index / segment) * segment;
    let left = index + (size.slots - size.width) / 2;
    left = Math.min(Math.max(left, start + POST_CLEARANCE), start + segment - POST_CLEARANCE - size.width);
    result.push({ type: slots[index], left, right: left + size.width, bottom: size.bottom, top: size.bottom + size.height });
    index += size.slots;
  }

  return result;
}

/**
 * Each wall is built along local +x from its left end (seen from outside) with its outside facing local +z,
 * then turned into place around the unit's centre.
 */
const WALLS = {
  front: { start: (length, depth) => [-length / 2, depth / 2], angle: 0, width: 'length' },
  right: { start: (length, depth) => [length / 2, depth / 2], angle: Math.PI / 2, width: 'depth' },
  back: { start: (length, depth) => [length / 2, -depth / 2], angle: Math.PI, width: 'length' },
  left: { start: (length, depth) => [-length / 2, -depth / 2], angle: -Math.PI / 2, width: 'depth' },
};

/**
 * Darken (factor < 1) or lighten (factor > 1) a hex colour, the same way UnitRenderer::shade() does.
 */
function shade(hex, factor) {
  const channels = hex.slice(1).match(/../g).map((channel) => parseInt(channel, 16));
  const shaded = channels.map((channel) => Math.round(factor <= 1 ? channel * factor : channel + (255 - channel) * (factor - 1)));

  return `#${shaded.map((channel) => Math.max(0, Math.min(255, channel)).toString(16).padStart(2, '0')).join('')}`;
}

/**
 * A bump map with four rounded ribs per metre, so the cladding reads as profiled steel sheet.
 */
function createRibTexture() {
  const canvas = document.createElement('canvas');
  canvas.width = 64;
  canvas.height = 4;
  const context = canvas.getContext('2d');

  for (let x = 0; x < canvas.width; x++) {
    const value = Math.round(127.5 + 127.5 * Math.cos((2 * Math.PI * x) / 16));
    context.fillStyle = `rgb(${value}, ${value}, ${value})`;
    context.fillRect(x, 0, 1, canvas.height);
  }

  const texture = new CanvasTexture(canvas);
  texture.wrapS = RepeatWrapping;
  texture.wrapT = RepeatWrapping;

  return texture;
}

/**
 * Asphalt: light grey speckles that darken the ground colour, from a fixed seed so photos come out the same every time.
 */
function createAsphaltTexture() {
  const canvas = document.createElement('canvas');
  canvas.width = 128;
  canvas.height = 128;
  const context = canvas.getContext('2d');
  context.fillStyle = '#c4c4c4';
  context.fillRect(0, 0, canvas.width, canvas.height);

  let seed = 7;
  const random = () => {
    seed = (seed * 16807) % 2147483647;

    return seed / 2147483647;
  };
  for (let i = 0; i < 2600; i++) {
    const value = Math.round(150 + random() * 100);
    context.fillStyle = `rgb(${value}, ${value}, ${value})`;
    context.fillRect(Math.floor(random() * canvas.width), Math.floor(random() * canvas.height), 1, 1);
  }

  const texture = new CanvasTexture(canvas);
  texture.wrapS = RepeatWrapping;
  texture.wrapT = RepeatWrapping;
  texture.repeat.set(90, 90);
  texture.colorSpace = SRGBColorSpace;

  return texture;
}

/** Business halls around the site: [angle in degrees, distance, width, depth, height, colour]. */
const HALLS = [
  [180, 55, 40, 25, 10, '#8f989e'], [150, 62, 30, 20, 8, '#5d7b93'], [212, 50, 28, 18, 7, '#b5babd'],
  [250, 66, 45, 25, 11, '#7d868c'], [112, 58, 26, 20, 8, '#a9b0b4'], [0, 60, 40, 25, 10, '#8f989e'],
  [38, 56, 30, 20, 8, '#5d7b93'], [322, 52, 28, 18, 7, '#b5babd'], [72, 66, 35, 25, 9, '#7d868c'],
  [290, 60, 30, 20, 8, '#a9b0b4'],
];

/** Trees along the edges of the site: [x, z]. */
const TREES = [[-16, -20], [-10, -24], [14, -22], [22, -14], [-24, 6], [24, 10], [-20, 18]];

/**
 * A Dutch business park around the unit: halls in the distance, a few trees and parking bays beside the unit.
 */
function buildIndustrialScenery({ length }) {
  const group = new Group();
  const markings = new MeshStandardMaterial({ color: '#e6e6e1', roughness: 0.8 });
  const trim = new MeshStandardMaterial({ color: '#4a5056', roughness: 0.7 });
  const trunk = new MeshStandardMaterial({ color: '#5b4a3b', roughness: 1 });
  const crown = new MeshStandardMaterial({ color: '#4d6a3c', roughness: 1 });
  const flat = (geometry, material, x, y, z) => {
    const mesh = new Mesh(geometry, material);
    mesh.position.set(x, y, z);

    return mesh;
  };

  HALLS.forEach(([degrees, distance, width, depth, height, color]) => {
    const angle = (degrees * Math.PI) / 180;
    const hall = new Group();
    hall.position.set(Math.sin(angle) * distance, 0, Math.cos(angle) * distance);
    hall.rotation.y = angle;
    hall.add(
      flat(new BoxGeometry(width, height, depth), new MeshStandardMaterial({ color, roughness: 0.6, metalness: 0.2 }), 0, height / 2, 0),
      flat(new BoxGeometry(width + 0.3, 0.6, depth + 0.3), trim, 0, height - 0.3, 0),
    );
    group.add(hall);
  });

  TREES.forEach(([x, z]) => {
    group.add(
      flat(new CylinderGeometry(0.18, 0.25, 3, 8), trunk, x, 1.5, z),
      flat(new SphereGeometry(2.2, 16, 12), crown, x, 4.2, z),
    );
  });

  const bayWidth = 2.6;
  const firstLine = length / 2 + 3;
  for (let bay = 0; bay <= 5; bay++) {
    group.add(flat(new BoxGeometry(0.12, 0.01, 5), markings, firstLine + bay * bayWidth, 0.005, 0));
  }
  group.add(flat(new BoxGeometry(5 * bayWidth + 0.12, 0.01, 0.12), markings, firstLine + 2.5 * bayWidth, 0.005, -2.5));

  return group;
}

function box(width, height, depth, x, y, z, material) {
  const mesh = new Mesh(new BoxGeometry(width, height, depth), material);
  mesh.position.set(x, y, z);
  mesh.castShadow = true;
  mesh.receiveShadow = true;

  return mesh;
}

function createMaterials(colors, ribs) {
  return {
    cladding: new MeshStandardMaterial({ color: colors.cladding, roughness: 0.55, metalness: 0.25, bumpMap: ribs, bumpScale: 1.5 }),
    frame: new MeshStandardMaterial({ color: colors.frame, roughness: 0.45, metalness: 0.35 }),
    doorLeaf: new MeshStandardMaterial({ color: shade(colors.frame, 1.25), roughness: 0.5, metalness: 0.3 }),
    glass: new MeshStandardMaterial({ color: '#8fb3c9', roughness: 0.08, metalness: 0.9, transparent: true, opacity: 0.75 }),
    handle: new MeshStandardMaterial({ color: '#d9d9d9', roughness: 0.3, metalness: 0.8 }),
    lining: new MeshStandardMaterial({ color: '#efeee9', roughness: 0.85 }),
    floor: new MeshStandardMaterial({ color: '#d6d5d0', roughness: 0.9 }),
    concrete: new MeshStandardMaterial({ color: '#a3a19b', roughness: 0.95 }),
    steel: new MeshStandardMaterial({ color: '#a7adb1', roughness: 0.45, metalness: 0.6 }),
  };
}

function buildWindow({ left, right, bottom, top }, materials) {
  const width = right - left;
  const height = top - bottom;
  const centerX = (left + right) / 2;
  const centerY = (bottom + top) / 2;
  const group = new Group();

  group.add(
    box(width, height, 0.01, centerX, centerY, -CLADDING_THICKNESS / 2, materials.glass),
    box(0.05, height, 0.08, left + 0.025, centerY, -0.02, materials.frame),
    box(0.05, height, 0.08, right - 0.025, centerY, -0.02, materials.frame),
    box(width, 0.05, 0.08, centerX, bottom + 0.025, -0.02, materials.frame),
    box(width, 0.05, 0.08, centerX, top - 0.025, -0.02, materials.frame),
    box(0.04, height, 0.06, centerX, centerY, -0.02, materials.frame),
  );

  return group;
}

/**
 * A door: the frame is as thick as the difference between its outside size and the clear opening.
 */
function buildDoor({ left, right, bottom, top }, materials) {
  const width = right - left;
  const height = top - bottom;
  const centerX = (left + right) / 2;
  const jamb = (width - DOOR_CLEAR.width) / 2;
  const head = height - DOOR_CLEAR.height;
  const group = new Group();

  group.add(
    box(width, height, 0.04, centerX, (bottom + top) / 2, -0.03, materials.doorLeaf),
    box(jamb, height, 0.08, left + jamb / 2, (bottom + top) / 2, -0.02, materials.frame),
    box(jamb, height, 0.08, right - jamb / 2, (bottom + top) / 2, -0.02, materials.frame),
    box(width, head, 0.08, centerX, top - head / 2, -0.02, materials.frame),
    box(0.08, 0.03, 0.05, right - 0.14, 1.09, 0.01, materials.handle),
  );

  return group;
}

/**
 * One wall: a cladding panel and a white inner lining with real openings, filled with windows and doors.
 * The unit has no roof, so the top of both layers shows the thickness of the wall.
 */
function buildWall(width, slots, segment, materials) {
  const shape = new Shape();
  shape.moveTo(0, 0);
  shape.lineTo(width, 0);
  shape.lineTo(width, HEIGHT);
  shape.lineTo(0, HEIGHT);
  shape.closePath();

  const group = new Group();

  openings(slots, segment).forEach((opening) => {
    const hole = new Path();
    hole.moveTo(opening.left, opening.bottom);
    hole.lineTo(opening.right, opening.bottom);
    hole.lineTo(opening.right, opening.top);
    hole.lineTo(opening.left, opening.top);
    hole.closePath();
    shape.holes.push(hole);

    group.add(opening.type === 'door' ? buildDoor(opening, materials) : buildWindow(opening, materials));
  });

  [[materials.cladding, CLADDING_THICKNESS, -CLADDING_THICKNESS], [materials.lining, LINING_THICKNESS, -CLADDING_THICKNESS - LINING_THICKNESS]]
    .forEach(([material, thickness, z]) => {
      const layer = new Mesh(new ExtrudeGeometry(shape, { depth: thickness, bevelEnabled: false }), material);
      layer.position.z = z;
      layer.castShadow = true;
      layer.receiveShadow = true;
      group.add(layer);
    });

  return group;
}

/**
 * The steel frame around the walls: corner posts, top and bottom rails and corner castings, without a roof
 * so the empty inside of the unit can be seen.
 */
function buildFrame(length, depth, seams, materials) {
  const group = new Group();
  const postX = length / 2 - POST_SIZE / 2 + 0.02;
  const postZ = depth / 2 - POST_SIZE / 2 + 0.02;

  [[-1, -1], [-1, 1], [1, -1], [1, 1]].forEach(([signX, signZ]) => {
    group.add(box(POST_SIZE, HEIGHT, POST_SIZE, signX * postX, HEIGHT / 2, signZ * postZ, materials.frame));
  });

  // The top rail and castings stand 1 cm proud of the walls, so their top faces never overlap.
  [[TOP_RAIL, HEIGHT - TOP_RAIL / 2 + 0.01], [BOTTOM_RAIL, BOTTOM_RAIL / 2]].forEach(([railHeight, y]) => {
    group.add(
      box(length, railHeight, 0.08, 0, y, depth / 2 - 0.02, materials.frame),
      box(length, railHeight, 0.08, 0, y, -depth / 2 + 0.02, materials.frame),
      box(0.08, railHeight, depth, length / 2 - 0.02, y, 0, materials.frame),
      box(0.08, railHeight, depth, -length / 2 + 0.02, y, 0, materials.frame),
    );
  });

  const castingX = length / 2 - CORNER_CASTING / 2 + 0.025;
  const castingZ = depth / 2 - CORNER_CASTING / 2 + 0.025;
  [0.06, HEIGHT - 0.05].forEach((y) => {
    [[-1, -1], [-1, 1], [1, -1], [1, 1]].forEach(([signX, signZ]) => {
      group.add(box(CORNER_CASTING, 0.12, CORNER_CASTING, signX * castingX, y, signZ * castingZ, materials.frame));
    });
  });

  // Where two units meet, their frames show as a wide post in each side wall and a beam across the open space.
  seams.forEach((z) => {
    [-1, 1].forEach((signX) => {
      group.add(
        box(POST_SIZE, HEIGHT, 0.2, signX * postX, HEIGHT / 2, z, materials.frame),
        box(CORNER_CASTING, 0.12, 0.24, signX * castingX, HEIGHT - 0.05, z, materials.frame),
      );
    });
    group.add(box(length, TOP_RAIL, 0.16, 0, HEIGHT - TOP_RAIL / 2 + 0.01, z, materials.frame));
  });

  return group;
}

const STAIR_WIDTH = 0.9;
const LANDING_WIDTH = 1.2;
const LANDING_DEPTH = 1.0;
const STEP_RISE = 0.18;
const STEP_GOING = 0.25;
const RAIL_HEIGHT = 1.0;

/**
 * An outside staircase to a door on the upper floor, in the wall's own coordinates (x along the wall, z outwards):
 * a landing in front of the door and a flight down to the ground. The flight runs along the wall towards the side
 * with the most room, or straight out from the wall when it does not fit alongside.
 */
function buildStairs(doorCenter, wallWidth, sill, ground, materials) {
  const group = new Group();
  const rise = sill - ground;
  const steps = Math.ceil(rise / STEP_RISE);
  const stepRise = rise / steps;
  const run = (steps - 1) * STEP_GOING;
  const landingZ = LANDING_DEPTH / 2 + 0.02;

  group.add(box(LANDING_WIDTH, 0.06, LANDING_DEPTH, doorCenter, sill - 0.03, landingZ, materials.steel));
  [-1, 1].forEach((side) => {
    const x = doorCenter + side * (LANDING_WIDTH / 2 - 0.04);
    group.add(box(0.08, rise, 0.08, x, ground + rise / 2, LANDING_DEPTH - 0.02, materials.steel));
  });

  const roomLeft = doorCenter - LANDING_WIDTH / 2;
  const roomRight = wallWidth - (doorCenter + LANDING_WIDTH / 2);
  const alongWall = Math.max(roomLeft, roomRight) + 0.3 >= run;
  const direction = roomRight >= roomLeft ? 1 : -1;
  const slope = Math.atan2(rise, run);
  const stringer = Math.hypot(rise, run);

  if (alongWall) {
    const start = doorCenter + direction * (LANDING_WIDTH / 2);
    for (let step = 0; step < steps - 1; step++) {
      const x = start + direction * (run - step * STEP_GOING - STEP_GOING / 2);
      group.add(box(STEP_GOING, 0.04, STAIR_WIDTH, x, ground + (step + 1) * stepRise - 0.02, landingZ, materials.steel));
    }
    [0.04, STAIR_WIDTH - 0.04].forEach((offset) => {
      const beam = box(stringer, 0.2, 0.04, start + direction * (run / 2), ground + rise / 2, landingZ - STAIR_WIDTH / 2 + offset, materials.steel);
      beam.rotation.z = -direction * slope;
      group.add(beam);
    });
    const rail = box(stringer, 0.04, 0.04, start + direction * (run / 2), ground + rise / 2 + RAIL_HEIGHT, landingZ + STAIR_WIDTH / 2, materials.steel);
    rail.rotation.z = -direction * slope;
    group.add(rail, box(0.04, RAIL_HEIGHT, 0.04, start + direction * run, ground + RAIL_HEIGHT / 2, landingZ + STAIR_WIDTH / 2, materials.steel));
    group.add(box(LANDING_WIDTH, 0.04, 0.04, doorCenter, sill + RAIL_HEIGHT, LANDING_DEPTH, materials.steel));
    group.add(box(0.04, RAIL_HEIGHT, 0.04, doorCenter - direction * (LANDING_WIDTH / 2 - 0.02), sill + RAIL_HEIGHT / 2, LANDING_DEPTH, materials.steel));
    group.add(box(0.04, 0.04, LANDING_DEPTH, doorCenter - direction * (LANDING_WIDTH / 2 - 0.02), sill + RAIL_HEIGHT, LANDING_DEPTH / 2, materials.steel));
  } else {
    const start = LANDING_DEPTH + 0.02;
    for (let step = 0; step < steps - 1; step++) {
      const z = start + run - step * STEP_GOING - STEP_GOING / 2;
      group.add(box(STAIR_WIDTH, 0.04, STEP_GOING, doorCenter, ground + (step + 1) * stepRise - 0.02, z, materials.steel));
    }
    [-1, 1].forEach((side) => {
      const x = doorCenter + side * (STAIR_WIDTH / 2 - 0.02);
      const beam = box(0.04, 0.2, stringer, x, ground + rise / 2, start + run / 2, materials.steel);
      beam.rotation.x = slope;
      const rail = box(0.04, 0.04, stringer, x, ground + rise / 2 + RAIL_HEIGHT, start + run / 2, materials.steel);
      rail.rotation.x = slope;
      group.add(beam, rail, box(0.04, RAIL_HEIGHT, 0.04, x, ground + RAIL_HEIGHT / 2, start + run, materials.steel));
      group.add(box(0.04, 0.04, LANDING_DEPTH, doorCenter + side * (LANDING_WIDTH / 2 - 0.02), sill + RAIL_HEIGHT, LANDING_DEPTH / 2, materials.steel));
    });
  }

  return group;
}

/** The sets of walls per layer, from the ground up, as in App\Support\UnitConfiguration::LEVELS. */
const LEVELS = ['walls', 'upper_walls'];

function buildUnit(configuration, dimensions, materials) {
  const { length, depth } = dimensions;
  const floors = configuration.floors ?? 1;
  const unit = new Group();

  const modules = configuration.modules ?? 1;
  const seams = Array.from({ length: modules - 1 }, (_, index) => depth / 2 - ((index + 1) * depth) / modules);
  const wallThickness = CLADDING_THICKNESS + LINING_THICKNESS;

  // Stacked layers stand on the top rail of the one below, which is 1 cm proud of its walls.
  LEVELS.slice(0, floors).forEach((level, floor) => {
    const body = new Group();
    body.position.y = RAISE + floor * (HEIGHT + 0.01);

    Object.entries(WALLS).forEach(([name, wall]) => {
      const [x, z] = wall.start(length, depth);
      const slots = configuration[level]?.[name] ?? [];
      const segment = wall.width === 'depth' ? depth / modules : length;
      const group = buildWall(dimensions[wall.width], slots, segment, materials);
      group.position.set(x, 0, z);
      group.rotation.y = wall.angle;
      body.add(group);

      if (floor > 0 && configuration.stairs) {
        openings(slots, segment).filter(({ type }) => type === 'door').forEach(({ left, right, bottom }) => {
          group.add(buildStairs((left + right) / 2, dimensions[wall.width], bottom, -body.position.y, materials));
        });
      }
    });

    body.add(buildFrame(length, depth, seams, materials));
    body.add(box(length - 2 * wallThickness, 0.03, depth - 2 * wallThickness, 0, BOTTOM_RAIL - 0.015, 0, materials.floor));
    unit.add(body);
  });

  [-depth / 2 + 0.15, ...seams, depth / 2 - 0.15].forEach((z) => {
    [-1, 1].forEach((signX) => {
      unit.add(box(0.3, RAISE, 0.3, signX * (length / 2 - 0.15), RAISE / 2, z, materials.concrete));
    });
  });

  return unit;
}

function dispose(object) {
  const materials = new Set();
  object.traverse((child) => {
    if (child.isMesh) {
      child.geometry.dispose();
      materials.add(child.material);
    }
  });
  materials.forEach((material) => material.dispose());
}

/**
 * Camera direction per view of App\Support\UnitRenderer::VIEWS: "front" looks mostly at the front wall with
 * the right wall just in view, "back" likewise at the back and left walls. Both look down into the open unit.
 */
const VIEW_DIRECTIONS = {
  front: [0.32, 0.95, 1],
  back: [-0.32, 0.95, -1],
};

/** Viewer-only camera that shows the front and right walls about equally, so a door in either is in view. */
const CORNER_DIRECTION = [0.8, 0.95, 1];

function createRenderer(options = {}) {
  const renderer = new WebGLRenderer({ antialias: true, ...options });
  renderer.toneMapping = NeutralToneMapping;
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = PCFShadowMap;

  return renderer;
}

/**
 * Lights, ground and backdrop around a unit. Showing another configuration replaces the previous unit.
 */
function createStage(renderer, backdrops) {
  const scene = new Scene();
  const pmrem = new PMREMGenerator(renderer);
  scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
  scene.environmentIntensity = 0.5;
  pmrem.dispose();

  const hemisphere = new HemisphereLight('#ffffff', '#ffffff', 0.8);
  const sun = new DirectionalLight('#fff6e8', 2.4);
  sun.castShadow = true;
  sun.shadow.mapSize.set(2048, 2048);
  sun.shadow.camera.left = -11;
  sun.shadow.camera.right = 11;
  sun.shadow.camera.top = 11;
  sun.shadow.camera.bottom = -11;
  sun.shadow.normalBias = 0.02;
  scene.add(hemisphere, sun);

  const groundMaterial = new MeshStandardMaterial({ roughness: 1 });
  const ground = new Mesh(new CircleGeometry(150, 64), groundMaterial);
  ground.rotation.x = -Math.PI / 2;
  ground.receiveShadow = true;
  scene.add(ground);

  const ribs = createRibTexture();
  const asphalt = createAsphaltTexture();
  let unit = null;
  let scenery = null;

  return {
    scene,

    show(configuration, dimensions) {
      const backdrop = backdrops[configuration.background] ?? Object.values(backdrops)[0];
      scene.background = new Color(backdrop.sky);
      scene.fog = new Fog(backdrop.horizon, 45, 140);
      groundMaterial.color.set(backdrop.ground);
      const isIndustrial = configuration.background === 'industrial';
      if ((groundMaterial.map !== null) !== isIndustrial) {
        groundMaterial.map = isIndustrial ? asphalt : null;
        groundMaterial.needsUpdate = true;
      }

      if (scenery) {
        scene.remove(scenery);
        dispose(scenery);
        scenery = null;
      }
      if (isIndustrial) {
        scenery = buildIndustrialScenery(dimensions);
        scene.add(scenery);
      }
      hemisphere.color.set(backdrop.sky);
      hemisphere.groundColor.set(backdrop.ground);

      if (unit) {
        scene.remove(unit);
        dispose(unit);
      }
      unit = buildUnit(configuration, dimensions, createMaterials(configuration.colors, ribs));
      scene.add(unit);
    },

    bounds() {
      return new Box3().setFromObject(unit);
    },

    /**
     * Put the sun over the shoulder of the camera of the given view, so the walls in view are lit.
     */
    light(view) {
      const [x, , z] = VIEW_DIRECTIONS[view];
      sun.position.set(-Math.sign(x) * 5, 9, Math.sign(z) * 8);
    },

    dispose() {
      if (unit) {
        dispose(unit);
      }
      if (scenery) {
        dispose(scenery);
      }
      dispose(ground);
      ribs.dispose();
      asphalt.dispose();
      scene.environment.dispose();
    },
  };
}

/**
 * Point the camera at everything that stands on the ground, outside stairs included, from the direction of the view.
 *
 * @param {Box3} bounds
 */
function aim(camera, target, bounds, view, zoom) {
  const size = bounds.getSize(new Vector3());
  const center = bounds.getCenter(new Vector3());
  const distance = ((size.x + size.z) * 1.3 + 3 + Math.max(0, size.y - 3) * 1.5) * zoom;
  const direction = new Vector3(...(view === 'corner' ? CORNER_DIRECTION : VIEW_DIRECTIONS[view])).normalize().multiplyScalar(distance);
  target.set(center.x, Math.min(1, size.y / 2) + Math.max(0, size.y - 3) * 0.6, center.z);
  camera.position.copy(target).add(direction);
  camera.lookAt(target);
}

/**
 * A rotatable 3D view of a unit configuration, rendered into the given element.
 *
 * @param {HTMLElement} container
 * @param {Record<string, {sky: string, horizon: string, ground: string, groundFar: string}>} backdrops
 * @param {{zoom?: boolean, distance?: number, view?: string}} options  Turn zooming off where the mouse wheel should keep
 *   scrolling the page; a distance below 1 frames the unit more tightly; view "corner" looks at the front and right walls alike.
 */
export function createUnitViewer(container, backdrops, { zoom = true, distance = 1, view = 'front' } = {}) {
  const renderer = createRenderer();
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  container.appendChild(renderer.domElement);

  const stage = createStage(renderer, backdrops);
  stage.light('front');

  const camera = new PerspectiveCamera(35, 16 / 9, 0.1, 200);
  const controls = new OrbitControls(camera, renderer.domElement);
  controls.enablePan = false;
  controls.enableZoom = zoom;
  controls.minDistance = 4;
  controls.maxDistance = 30;
  controls.maxPolarAngle = Math.PI / 2 - 0.05;

  let currentFootprint = null;

  const render = () => renderer.render(stage.scene, camera);
  controls.addEventListener('change', render);

  const resize = () => {
    const { clientWidth: width, clientHeight: height } = container;
    if (width === 0 || height === 0) {
      return;
    }

    renderer.setSize(width, height);
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
    render();
  };
  new ResizeObserver(resize).observe(container);

  return {
    /**
     * @param {{size: string, walls: Record<string, string[]>, colors: {cladding: string, frame: string}, background: string}} configuration
     * @param {{length: number, depth: number}} dimensions
     */
    update(configuration, dimensions) {
      stage.show(configuration, dimensions);

      // Reframe the camera when the building changes size; otherwise keep the angle the user turned to.
      const footprint = `${configuration.size}-${configuration.modules ?? 1}-${configuration.floors ?? 1}-${configuration.stairs ?? false}`;
      if (footprint !== currentFootprint) {
        currentFootprint = footprint;
        aim(camera, controls.target, stage.bounds(), view, distance);
        controls.update();
      }

      resize();
      render();
    },
  };
}

/**
 * Render a JPEG photo of every view, in the 3:2 format the website uses for unit photos.
 *
 * @param {{size: string, walls: Record<string, string[]>, colors: {cladding: string, frame: string}, background: string}} configuration
 * @param {{length: number, depth: number}} dimensions
 * @param {Record<string, {sky: string, horizon: string, ground: string, groundFar: string}>} backdrops
 * @returns {Promise<Record<string, Blob>>}
 */
export async function renderUnitPhotos(configuration, dimensions, backdrops, width = 1500, height = 1000) {
  const renderer = createRenderer({ preserveDrawingBuffer: true });
  renderer.setPixelRatio(1);
  renderer.setSize(width, height, false);

  const stage = createStage(renderer, backdrops);
  stage.show(configuration, dimensions);

  const camera = new PerspectiveCamera(30, width / height, 0.1, 200);
  const target = new Vector3();
  const photos = {};

  try {
    for (const view of Object.keys(VIEW_DIRECTIONS)) {
      stage.light(view);
      aim(camera, target, stage.bounds(), view, 0.95);
      renderer.render(stage.scene, camera);
      photos[view] = await new Promise((resolve, reject) => {
        renderer.domElement.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('3D-foto maken mislukt.'))), 'image/jpeg', 0.88);
      });
    }
  } finally {
    stage.dispose();
    renderer.dispose();
    renderer.forceContextLoss();
  }

  return photos;
}
