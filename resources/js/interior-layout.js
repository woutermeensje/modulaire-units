/**
 * Where the interior of one layer goes, matching App\Support\InteriorLayout: ceiling lights, a toilet cubicle and a
 * kitchenette on the ground floor, and an airco per unit on every layer.
 *
 * Positions are in metres on the floor plan seen from above, over the nominal size of all coupled units: x from the
 * left wall, y from the back wall.
 */
export const INTERIOR_WALL = 0.1;
export const TOILET = { width: 1.0, depth: 1.5 };
export const KITCHEN = { length: 1.8, depth: 0.6 };
export const AIRCO = { length: 0.9, depth: 0.25 };

const EPSILON = 0.001;
const W = INTERIOR_WALL;

// Whether a slot of one of the given types lies along a stretch of a wall, in plan coordinates along that wall.
function hasOpening(walls, wall, from, to, length, depth, types) {
  const [start, end] = {
    front: [from, to],
    left: [from, to],
    back: [length - to, length - from],
    right: [depth - to, depth - from],
  }[wall];

  for (let index = Math.floor(start + EPSILON); index < end - EPSILON; index++) {
    if (types.includes(walls[wall]?.[index] ?? 'empty')) {
      return true;
    }
  }

  return false;
}

const overlapsAny = (item, placed) => placed.some((other) => item.x1 < other.x2 - EPSILON && other.x1 < item.x2 - EPSILON
  && item.y1 < other.y2 - EPSILON && other.y1 < item.y2 - EPSILON);

function toilet(walls, length, depth) {
  const candidates = [['back', 'left'], ['back', 'right'], ['front', 'left'], ['front', 'right']].map(([wall, side]) => {
    const [x1, x2] = side === 'left' ? [W, W + TOILET.width] : [length - W - TOILET.width, length - W];
    const [y1, y2] = wall === 'back' ? [W, W + TOILET.depth] : [depth - W - TOILET.depth, depth - W];

    return { type: 'toilet', wall, side, x1, y1, x2, y2 };
  });

  return candidates.find((item) => !hasOpening(walls, item.wall, item.x1, item.x2, length, depth, ['door'])
    && !hasOpening(walls, item.side, item.y1, item.y2, length, depth, ['door'])) ?? candidates[0];
}

function kitchen(walls, length, depth, placed) {
  const candidates = ['back', 'front'].flatMap((wall) => ['right', 'left'].map((align) => {
    const [x1, x2] = align === 'right' ? [length - W - KITCHEN.length, length - W] : [W, W + KITCHEN.length];
    const [y1, y2] = wall === 'back' ? [W, W + KITCHEN.depth] : [depth - W - KITCHEN.depth, depth - W];

    return { type: 'kitchen', wall, x1, y1, x2, y2 };
  }));
  const free = candidates.filter((item) => !overlapsAny(item, placed));

  return free.find((item) => !hasOpening(walls, item.wall, item.x1, item.x2, length, depth, ['door'])) ?? free[0] ?? candidates[0];
}

function airco(walls, length, depth, center, placed) {
  const y1 = center - AIRCO.length / 2;
  const y2 = center + AIRCO.length / 2;
  const candidates = ['right', 'left'].map((side) => {
    const [x1, x2] = side === 'right' ? [length - W - AIRCO.depth, length - W] : [W, W + AIRCO.depth];

    return { type: 'airco', wall: side, x1, y1, x2, y2 };
  });

  return candidates.find((item) => !hasOpening(walls, item.wall, item.y1, item.y2, length, depth, ['door', 'window'])
    && !overlapsAny(item, placed)) ?? candidates[0];
}

/**
 * @param {{modules?: number, interior?: string[]}} configuration
 * @param {{length: number, depth: number}} dimensions  Nominal size of all coupled units together.
 * @param {string} level  "walls" for the ground floor, "upper_walls" for the second layer.
 * @returns {{lights: {x: number, y: number}[], items: {type: string, wall: string, side?: string, x1: number, y1: number, x2: number, y2: number}[]}}
 */
export function interiorLayout(configuration, { length, depth }, level) {
  const modules = configuration.modules ?? 1;
  const unitDepth = depth / modules;
  const walls = configuration[level] ?? {};
  const interior = configuration.interior ?? [];
  const isGroundFloor = level === 'walls';

  const lightsPerUnit = Math.floor(length / 3);
  const lights = [];
  for (let module = 0; module < modules; module++) {
    for (let light = 0; light < lightsPerUnit; light++) {
      lights.push({ x: ((light + 0.5) * length) / lightsPerUnit, y: (module + 0.5) * unitDepth });
    }
  }

  const items = [];
  if (isGroundFloor && interior.includes('toilet')) {
    items.push(toilet(walls, length, depth));
  }
  if (isGroundFloor && interior.includes('kitchen')) {
    items.push(kitchen(walls, length, depth, items));
  }
  if (interior.includes('airco')) {
    for (let module = 0; module < modules; module++) {
      items.push(airco(walls, length, depth, (module + 0.5) * unitDepth, items));
    }
  }

  return { lights, items };
}
