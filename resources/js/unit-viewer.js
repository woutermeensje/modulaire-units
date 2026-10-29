import {
  BackSide,
  BoxGeometry,
  CanvasTexture,
  CircleGeometry,
  Color,
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
  Vector3,
  WebGLRenderer,
} from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';

// Dimensions in metres, matching App\Support\UnitRenderer.
const HEIGHT = 2.6;
const CLADDING_THICKNESS = 0.05;
const POST_SIZE = 0.14;
const TOP_RAIL = 0.2;
const BOTTOM_RAIL = 0.15;
const RAISE = 0.1;

const OPENINGS = {
  window: { left: 0.15, right: 0.85, bottom: 0.9, top: 2.1 },
  door: { left: 0.12, right: 0.88, bottom: 0.15, top: 2.2 },
};

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
    roof: new MeshStandardMaterial({ color: shade(colors.frame, 1.35), roughness: 0.7, metalness: 0.2 }),
    doorLeaf: new MeshStandardMaterial({ color: shade(colors.frame, 1.25), roughness: 0.5, metalness: 0.3 }),
    glass: new MeshStandardMaterial({ color: '#8fb3c9', roughness: 0.08, metalness: 0.9, transparent: true, opacity: 0.75 }),
    handle: new MeshStandardMaterial({ color: '#d9d9d9', roughness: 0.3, metalness: 0.8 }),
    interior: new MeshStandardMaterial({ color: '#ece9e2', roughness: 0.9, side: BackSide }),
    concrete: new MeshStandardMaterial({ color: '#a3a19b', roughness: 0.95 }),
  };
}

function buildWindow(index, materials) {
  const { left, right, bottom, top } = OPENINGS.window;
  const width = right - left;
  const height = top - bottom;
  const centerX = index + (left + right) / 2;
  const centerY = (bottom + top) / 2;
  const group = new Group();

  group.add(
    box(width, height, 0.01, centerX, centerY, -CLADDING_THICKNESS / 2, materials.glass),
    box(0.05, height, 0.08, index + left + 0.025, centerY, -0.02, materials.frame),
    box(0.05, height, 0.08, index + right - 0.025, centerY, -0.02, materials.frame),
    box(width, 0.05, 0.08, centerX, bottom + 0.025, -0.02, materials.frame),
    box(width, 0.05, 0.08, centerX, top - 0.025, -0.02, materials.frame),
    box(0.04, height, 0.06, centerX, centerY, -0.02, materials.frame),
  );

  return group;
}

function buildDoor(index, materials) {
  const { left, right, bottom, top } = OPENINGS.door;
  const width = right - left;
  const height = top - bottom;
  const centerX = index + (left + right) / 2;
  const group = new Group();

  group.add(
    box(width, height, 0.04, centerX, (bottom + top) / 2, -0.03, materials.doorLeaf),
    box(0.05, height, 0.08, index + left + 0.025, (bottom + top) / 2, -0.02, materials.frame),
    box(0.05, height, 0.08, index + right - 0.025, (bottom + top) / 2, -0.02, materials.frame),
    box(width, 0.05, 0.08, centerX, top - 0.025, -0.02, materials.frame),
    box(0.08, 0.03, 0.05, index + 0.74, 1.04, 0.01, materials.handle),
  );

  return group;
}

/**
 * One wall: a cladding panel with real openings, filled with windows and doors.
 */
function buildWall(width, slots, materials) {
  const shape = new Shape();
  shape.moveTo(0, 0);
  shape.lineTo(width, 0);
  shape.lineTo(width, HEIGHT);
  shape.lineTo(0, HEIGHT);
  shape.closePath();

  const group = new Group();

  slots.forEach((slot, index) => {
    const opening = OPENINGS[slot];
    if (!opening) {
      return;
    }

    const hole = new Path();
    hole.moveTo(index + opening.left, opening.bottom);
    hole.lineTo(index + opening.right, opening.bottom);
    hole.lineTo(index + opening.right, opening.top);
    hole.lineTo(index + opening.left, opening.top);
    hole.closePath();
    shape.holes.push(hole);

    group.add(slot === 'door' ? buildDoor(index, materials) : buildWindow(index, materials));
  });

  const panel = new Mesh(new ExtrudeGeometry(shape, { depth: CLADDING_THICKNESS, bevelEnabled: false }), materials.cladding);
  panel.position.z = -CLADDING_THICKNESS;
  panel.castShadow = true;
  panel.receiveShadow = true;
  group.add(panel);

  return group;
}

/**
 * The steel frame around the walls: corner posts, top and bottom rails and the roof.
 */
function buildFrame(length, depth, materials) {
  const group = new Group();
  const postX = length / 2 - POST_SIZE / 2 + 0.02;
  const postZ = depth / 2 - POST_SIZE / 2 + 0.02;

  [[-1, -1], [-1, 1], [1, -1], [1, 1]].forEach(([signX, signZ]) => {
    group.add(box(POST_SIZE, HEIGHT, POST_SIZE, signX * postX, HEIGHT / 2, signZ * postZ, materials.frame));
  });

  [[TOP_RAIL, HEIGHT - TOP_RAIL / 2], [BOTTOM_RAIL, BOTTOM_RAIL / 2]].forEach(([railHeight, y]) => {
    group.add(
      box(length, railHeight, 0.1, 0, y, depth / 2 - 0.03, materials.frame),
      box(length, railHeight, 0.1, 0, y, -depth / 2 + 0.03, materials.frame),
      box(0.1, railHeight, depth, length / 2 - 0.03, y, 0, materials.frame),
      box(0.1, railHeight, depth, -length / 2 + 0.03, y, 0, materials.frame),
    );
  });

  group.add(box(length + 0.06, 0.06, depth + 0.06, 0, HEIGHT + 0.03, 0, materials.roof));

  return group;
}

function buildUnit(configuration, dimensions, materials) {
  const { length, depth } = dimensions;
  const unit = new Group();
  const body = new Group();
  body.position.y = RAISE;

  Object.entries(WALLS).forEach(([name, wall]) => {
    const [x, z] = wall.start(length, depth);
    const group = buildWall(dimensions[wall.width], configuration.walls[name] ?? [], materials);
    group.position.set(x, 0, z);
    group.rotation.y = wall.angle;
    body.add(group);
  });

  body.add(buildFrame(length, depth, materials));

  const interior = new Mesh(new BoxGeometry(length - 0.12, HEIGHT - 0.02, depth - 0.12), materials.interior);
  interior.position.y = HEIGHT / 2;
  body.add(interior);

  [[-1, -1], [-1, 1], [1, -1], [1, 1]].forEach(([signX, signZ]) => {
    unit.add(box(0.3, RAISE, 0.3, signX * (length / 2 - 0.15), RAISE / 2, signZ * (depth / 2 - 0.15), materials.concrete));
  });

  unit.add(body);

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
 * Camera direction per view of App\Support\UnitRenderer::VIEWS: "front" looks at the front and right walls,
 * "back" at the back and left walls.
 */
const VIEW_DIRECTIONS = {
  front: [0.55, 0.35, 0.76],
  back: [-0.55, 0.35, -0.76],
};

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
  sun.shadow.camera.left = -7;
  sun.shadow.camera.right = 7;
  sun.shadow.camera.top = 7;
  sun.shadow.camera.bottom = -7;
  sun.shadow.normalBias = 0.02;
  scene.add(hemisphere, sun);

  const groundMaterial = new MeshStandardMaterial({ roughness: 1 });
  const ground = new Mesh(new CircleGeometry(80, 64), groundMaterial);
  ground.rotation.x = -Math.PI / 2;
  ground.receiveShadow = true;
  scene.add(ground);

  const ribs = createRibTexture();
  let unit = null;

  return {
    scene,

    show(configuration, dimensions) {
      const backdrop = backdrops[configuration.background] ?? Object.values(backdrops)[0];
      scene.background = new Color(backdrop.sky);
      scene.fog = new Fog(backdrop.horizon, 20, 70);
      groundMaterial.color.set(backdrop.ground);
      hemisphere.color.set(backdrop.sky);
      hemisphere.groundColor.set(backdrop.ground);

      if (unit) {
        scene.remove(unit);
        dispose(unit);
      }
      unit = buildUnit(configuration, dimensions, createMaterials(configuration.colors, ribs));
      scene.add(unit);
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
      dispose(ground);
      ribs.dispose();
      scene.environment.dispose();
    },
  };
}

function aim(camera, target, { length, depth }, view, zoom) {
  const distance = ((length + depth) * 1.3 + 3) * zoom;
  const [x, y, z] = VIEW_DIRECTIONS[view];
  camera.position.set(distance * x, distance * y, distance * z);
  target.set(0, 1.2, 0);
  camera.lookAt(target);
}

/**
 * A rotatable 3D view of a unit configuration, rendered into the given element.
 *
 * @param {HTMLElement} container
 * @param {Record<string, {sky: string, horizon: string, ground: string, groundFar: string}>} backdrops
 * @param {{zoom?: boolean, distance?: number}} options  Turn zooming off where the mouse wheel should keep scrolling
 *   the page; a distance below 1 frames the unit more tightly.
 */
export function createUnitViewer(container, backdrops, { zoom = true, distance = 1 } = {}) {
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

  let currentSize = null;

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

      if (configuration.size !== currentSize) {
        currentSize = configuration.size;
        aim(camera, controls.target, dimensions, 'front', distance);
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
      aim(camera, target, dimensions, view, 0.95);
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
