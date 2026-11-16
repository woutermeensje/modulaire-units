/**
 * Where the interior of one layer goes, matching App\Support\InteriorLayout: ceiling lights, an airco and a heater per
 * unit on every layer, and on the ground floor the toilet cubicle, the kitchenette, furniture, wall sockets and network
 * points. Items that were dragged on the floor plan keep the position in configuration.positions.
 *
 * Positions are in metres on the floor plan seen from above, over the nominal size of all coupled units: x from the
 * left wall, y from the back wall.
 */
import { windowSpans } from './window-spans';

export const INTERIOR_WALL = 0.1;
export const TOILET = { width: 1.0, depth: 1.5 };
export const KITCHEN = { length: 1.8, depth: 0.6 };
export const AIRCO = { length: 0.9, depth: 0.25 };

/** Indicative sizes in metres, as App\Support\InteriorLayout::SIZES. */
export const SIZES = {
  toilet: { length: 1.0, depth: 1.5, bottom: 0.0, top: 2.1 },
  kitchen: { length: 1.8, depth: 0.6, bottom: 0.0, top: 0.9 },
  airco: { length: 0.9, depth: 0.25, bottom: 2.0, top: 2.3 },
  heater: { length: 1.0, depth: 0.12, bottom: 0.15, top: 0.75 },
  counter: { length: 1.2, depth: 0.6, bottom: 0.0, top: 1.1 },
  worktop: { length: 1.6, depth: 0.6, bottom: 0.0, top: 0.9 },
  cabinet: { length: 0.8, depth: 0.45, bottom: 0.0, top: 2.0 },
  desk: { length: 1.4, depth: 0.7, bottom: 0.0, top: 0.75 },
  chair: { length: 0.6, depth: 0.6, bottom: 0.0, top: 0.9 },
  socket: { length: 0.16, depth: 0.06, bottom: 0.3, top: 0.38 },
  network: { length: 0.16, depth: 0.06, bottom: 0.3, top: 0.38 },
};

export const FREE = ['desk', 'chair'];
const POINTS = ['socket', 'network'];

const AVOID = {
  toilet: ['door'],
  kitchen: ['door', 'hatch'],
  airco: ['door', 'window', 'single_window', 'hatch'],
  heater: ['door', 'hatch'],
  counter: ['door'],
  worktop: ['door', 'hatch'],
  cabinet: ['door', 'window', 'single_window', 'hatch'],
  desk: ['door', 'hatch'],
  chair: ['door'],
  socket: ['door', 'hatch'],
  network: ['door', 'hatch'],
};

// Window and door sizes and placement, matching App\Support\UnitConfiguration::OPENING_SIZES and openings().
export const OPENING_SIZES = {
  window: { width: 1.8, height: 1.2, bottom: 0.9, slots: 2 },
  single_window: { width: 0.945, height: 1.2, bottom: 0.9, slots: 1 },
  door: { width: 0.875, height: 2.125, bottom: 0.15, slots: 1 },
  hatch: { width: 0.8, height: 0.7, bottom: 1.2, slots: 1 },
};
export const POST_CLEARANCE = 0.12;

const SOCKET_STEP = 0.5;
const EPSILON = 0.001;
const W = INTERIOR_WALL;
const isFrontOrBack = (wall) => wall === 'front' || wall === 'back';

/**
 * Where the windows, doors and hatches of a wall are, in metres from its left end (seen from outside), each centred
 * on its slots but kept clear of the posts at wall ends and at seams between units every `segment` slots.
 */
export function openings(slots, segment) {
  const result = [];
  let index = 0;

  while (index < slots.length) {
    const type = slots[index];
    const size = OPENING_SIZES[type];
    if (!size) {
      index++;
      continue;
    }

    const start = Math.floor(index / segment) * segment;
    let spans = [size.slots];

    if (type === 'window') {
      let end = index;
      while (end < Math.min(slots.length, start + segment) && slots[end] === 'window') {
        end++;
      }
      spans = windowSpans(end - index);
      if (spans.length === 0) {
        index = end;
        continue;
      }
    }

    spans.forEach((span) => {
      let left = index + (span - size.width) / 2;
      left = Math.min(Math.max(left, start + POST_CLEARANCE), start + segment - POST_CLEARANCE - size.width);
      result.push({ type, left, right: left + size.width, bottom: size.bottom, top: size.bottom + size.height });
      index += span;
    });
  }

  return result;
}

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

const blocks = (walls, item, types, length, depth) => (isFrontOrBack(item.wall)
  ? hasOpening(walls, item.wall, item.x1, item.x2, length, depth, types)
  : hasOpening(walls, item.wall, item.y1, item.y2, length, depth, types));

function overlapsAny(item, placed) {
  if (POINTS.includes(item.type)) {
    return false;
  }

  const size = SIZES[item.type];

  return placed.some((other) => {
    // A chair is moved aside or tucked under, so it never pushes anything else away.
    if (POINTS.includes(other.type) || other.type === 'chair') {
      return false;
    }
    const otherSize = SIZES[other.type];

    return item.x1 < other.x2 - EPSILON && other.x1 < item.x2 - EPSILON
      && item.y1 < other.y2 - EPSILON && other.y1 < item.y2 - EPSILON
      && size.bottom < otherSize.top - EPSILON && otherSize.bottom < size.top - EPSILON;
  });
}

function corner(id, wall, side, length, depth) {
  const [x1, x2] = side === 'left' ? [W, W + TOILET.width] : [length - W - TOILET.width, length - W];
  const [y1, y2] = wall === 'back' ? [W, W + TOILET.depth] : [depth - W - TOILET.depth, depth - W];

  return { id, type: 'toilet', wall, side, x1, y1, x2, y2 };
}

function againstWall(id, type, wall, along, length, depth) {
  const { length: itemLength, depth: itemDepth } = SIZES[type];
  const wallLength = isFrontOrBack(wall) ? length : depth;
  const center = Math.max(W + itemLength / 2, Math.min(wallLength - W - itemLength / 2, along));
  const from = center - itemLength / 2;
  const to = center + itemLength / 2;
  const [x1, y1, x2, y2] = {
    back: [from, W, to, W + itemDepth],
    front: [from, depth - W - itemDepth, to, depth - W],
    left: [W, from, W + itemDepth, to],
    right: [length - W - itemDepth, from, length - W, to],
  }[wall];

  return { id, type, wall, x1, y1, x2, y2 };
}

function free(id, type, wall, x, y, length, depth) {
  const { length: itemLength, depth: itemDepth } = SIZES[type];
  const [halfX, halfY] = isFrontOrBack(wall) ? [itemLength / 2, itemDepth / 2] : [itemDepth / 2, itemLength / 2];
  const centerX = Math.max(W + halfX, Math.min(length - W - halfX, x));
  const centerY = Math.max(W + halfY, Math.min(depth - W - halfY, y));

  return { id, type, wall, x1: centerX - halfX, y1: centerY - halfY, x2: centerX + halfX, y2: centerY + halfY };
}

/**
 * The wall nearest to a point on the plan, preferring back, front, left and right in that order on a tie.
 */
export function nearestWall(x, y, length, depth) {
  const distances = { back: y, front: depth - y, left: x, right: length - x };
  const shortest = Math.min(...Object.values(distances));

  return Object.keys(distances).find((wall) => distances[wall] === shortest);
}

function moved(id, type, { x, y }, length, depth) {
  if (type === 'toilet') {
    return corner(id, y < depth / 2 ? 'back' : 'front', x < length / 2 ? 'left' : 'right', length, depth);
  }

  const wall = nearestWall(x, y, length, depth);

  return FREE.includes(type) ? free(id, type, wall, x, y, length, depth) : againstWall(id, type, wall, isFrontOrBack(wall) ? x : y, length, depth);
}

function toilet(id, walls, length, depth, placed) {
  const candidates = [['back', 'left'], ['back', 'right'], ['front', 'left'], ['front', 'right']].map(([wall, side]) => corner(id, wall, side, length, depth));

  return candidates.find((item) => !hasOpening(walls, item.wall, item.x1, item.x2, length, depth, AVOID.toilet)
    && !hasOpening(walls, item.side, item.y1, item.y2, length, depth, AVOID.toilet)
    && !overlapsAny(item, placed)) ?? candidates[0];
}

function kitchen(id, walls, length, depth, placed) {
  const candidates = ['back', 'front'].flatMap((wall) => ['right', 'left'].map((align) => {
    const [x1, x2] = align === 'right' ? [length - W - KITCHEN.length, length - W] : [W, W + KITCHEN.length];
    const [y1, y2] = wall === 'back' ? [W, W + KITCHEN.depth] : [depth - W - KITCHEN.depth, depth - W];

    return { id, type: 'kitchen', wall, x1, y1, x2, y2 };
  }));
  const clear = candidates.filter((item) => !overlapsAny(item, placed));

  return clear.find((item) => !hasOpening(walls, item.wall, item.x1, item.x2, length, depth, AVOID.kitchen)) ?? clear[0] ?? candidates[0];
}

function onSideWall(id, type, sides, walls, length, depth, center, placed) {
  return sides
    .map((side) => againstWall(id, type, side, center, length, depth))
    .find((item) => !hasOpening(walls, item.wall, item.y1, item.y2, length, depth, AVOID[type]) && !overlapsAny(item, placed)) ?? null;
}

function alongWalls(id, type, order, walls, length, depth, placed) {
  const itemLength = SIZES[type].length;
  let clear = null;
  let first = null;

  for (const wall of order) {
    const wallLength = isFrontOrBack(wall) ? length : depth;
    const steps = Math.floor((wallLength - 2 * W - itemLength) * 10 + EPSILON);

    for (let step = 0; step <= steps; step++) {
      const item = againstWall(id, type, wall, W + itemLength / 2 + step / 10, length, depth);
      first ??= item;

      if (overlapsAny(item, placed)) {
        continue;
      }
      clear ??= item;

      if (!blocks(walls, item, AVOID[type], length, depth)) {
        return item;
      }
    }
  }

  return clear ?? first;
}

function openingCentres(walls, type, length, depth, unitDepth) {
  return ['front', 'right', 'back', 'left'].flatMap((wall) => {
    const wallLength = isFrontOrBack(wall) ? length : depth;
    const segment = isFrontOrBack(wall) ? length : unitDepth;

    return openings(walls[wall] ?? [], segment)
      .filter((opening) => opening.type === type)
      .map((opening) => {
        const middle = (opening.left + opening.right) / 2;

        return [wall, wall === 'back' || wall === 'right' ? wallLength - middle : middle];
      });
  });
}

function atOpening(id, type, centres, walls, length, depth, placed) {
  for (const [wall, along] of centres) {
    const item = againstWall(id, type, wall, along, length, depth);
    if (!blocks(walls, item, ['door'], length, depth) && !overlapsAny(item, placed)) {
      return item;
    }
  }

  return alongWalls(id, type, ['front', 'back', 'left', 'right'], walls, length, depth, placed);
}

function chair(id, index, walls, length, depth, placed) {
  const ofType = (type) => placed.filter((item) => item.type === type)
    .sort((a, b) => a.id.localeCompare(b.id, undefined, { numeric: true }));
  const tables = [...ofType('desk'), ...ofType('counter')];
  const table = tables[index];

  if (table) {
    const centerX = (table.x1 + table.x2) / 2;
    const centerY = (table.y1 + table.y2) / 2;
    const offset = SIZES.chair.depth / 2 - 0.15;
    const [x, y] = {
      back: [centerX, table.y2 + offset],
      front: [centerX, table.y1 - offset],
      left: [table.x2 + offset, centerY],
      right: [table.x1 - offset, centerY],
    }[table.wall];

    return free(id, 'chair', table.wall, x, y, length, depth);
  }

  return alongWalls(id, 'chair', ['back', 'front', 'left', 'right'], walls, length, depth, placed);
}

function connection(id, type, index, count, walls, length, depth, placed) {
  const cubicles = placed.filter((item) => item.type === 'toilet');
  const spots = [];

  ['front', 'right', 'back', 'left'].forEach((wall) => {
    const wallLength = isFrontOrBack(wall) ? length : depth;
    const spotCount = Math.round(wallLength / SOCKET_STEP) - 1;

    for (let spot = 1; spot <= spotCount; spot++) {
      const item = againstWall(id, type, wall, spot * SOCKET_STEP + (type === 'network' ? 0.25 : 0), length, depth);
      const inCubicle = cubicles.some((cubicle) => item.x1 < cubicle.x2 && cubicle.x1 < item.x2 && item.y1 < cubicle.y2 && cubicle.y1 < item.y2);
      if (!inCubicle && !blocks(walls, item, AVOID[type], length, depth)) {
        spots.push(item);
      }
    }
  });

  if (spots.length === 0) {
    return againstWall(id, type, 'back', length / 2, length, depth);
  }

  return spots[Math.floor(((index + 0.5) * spots.length) / count) % spots.length];
}

/**
 * Every item of one layer in the order it is placed, as [id, type].
 */
export function plannedItems(configuration, level) {
  const interior = configuration.interior ?? [];
  const modules = configuration.modules ?? 1;
  const isGroundFloor = level === 'walls';
  const counts = [];

  if (isGroundFloor && interior.includes('toilet')) {
    counts.push(['toilet', 1]);
  }
  if (isGroundFloor && interior.includes('kitchen')) {
    counts.push(['kitchen', 1]);
  }
  // Counters and desks before the climate units, so they get the hatches and windows they belong at; chairs last.
  const furniture = isGroundFloor ? configuration.furniture ?? {} : {};
  const addFurniture = (types) => types.forEach((type) => {
    if (furniture[type] > 0) {
      counts.push([type, furniture[type]]);
    }
  });
  addFurniture(['counter', 'desk']);
  ['airco', 'heater'].forEach((type) => {
    if (interior.includes(type)) {
      counts.push([type, modules]);
    }
  });
  addFurniture(['worktop', 'cabinet', 'chair']);
  if (isGroundFloor) {
    Object.entries(configuration.connections ?? {}).forEach(([type, count]) => {
      if (count > 0) {
        counts.push([type, count]);
      }
    });
  }

  return counts.flatMap(([type, count]) => Array.from({ length: count }, (_, index) => [`${level}:${type}:${index}`, type]));
}

/**
 * @param {{modules?: number, interior?: string[], furniture?: Record<string, number>, connections?: Record<string, number>, tentative?: string[], positions?: Record<string, {x: number, y: number}>}} configuration
 * @param {{length: number, depth: number}} dimensions  Nominal size of all coupled units together.
 * @param {string} level  "walls" for the ground floor, "upper_walls" for the second layer.
 * @returns {{lights: {x: number, y: number}[], items: {id: string, type: string, wall: string, side?: string, x1: number, y1: number, x2: number, y2: number, tentative: boolean}[]}}
 */
export function interiorLayout(configuration, { length, depth }, level) {
  const modules = configuration.modules ?? 1;
  const unitDepth = depth / modules;
  const walls = configuration[level] ?? {};
  const positions = configuration.positions ?? {};
  const tentative = configuration.tentative ?? [];

  const lightsPerUnit = Math.floor(length / 3);
  const lights = [];
  for (let module = 0; module < modules; module++) {
    for (let light = 0; light < lightsPerUnit; light++) {
      lights.push({ x: ((light + 0.5) * length) / lightsPerUnit, y: (module + 0.5) * unitDepth });
    }
  }

  const planned = plannedItems(configuration, level);
  const counts = {};
  planned.forEach(([, type]) => { counts[type] = (counts[type] ?? 0) + 1; });

  // Dragged items first, so everything placed automatically keeps clear of them.
  const placed = new Map();
  planned.forEach(([id, type]) => {
    if (positions[id]) {
      placed.set(id, moved(id, type, positions[id], length, depth));
    }
  });

  planned.forEach(([id, type]) => {
    if (placed.has(id)) {
      return;
    }

    const index = Number(id.slice(id.lastIndexOf(':') + 1));
    const others = [...placed.values()];
    const centre = (index + 0.5) * unitDepth;
    const centres = (openingType) => openingCentres(walls, openingType, length, depth, unitDepth);
    const item = {
      toilet: () => toilet(id, walls, length, depth, others),
      kitchen: () => kitchen(id, walls, length, depth, others),
      airco: () => onSideWall(id, 'airco', ['right', 'left'], walls, length, depth, centre, others) ?? againstWall(id, 'airco', 'right', centre, length, depth),
      heater: () => onSideWall(id, 'heater', ['left', 'right'], walls, length, depth, centre, others)
        ?? alongWalls(id, 'heater', ['left', 'right', 'back', 'front'], walls, length, depth, others),
      counter: () => atOpening(id, 'counter', [...centres('hatch'), ...centres('window'), ...centres('single_window')], walls, length, depth, others),
      desk: () => atOpening(id, 'desk', [...centres('window'), ...centres('single_window')], walls, length, depth, others),
      chair: () => chair(id, index, walls, length, depth, others),
      worktop: () => alongWalls(id, 'worktop', ['back', 'front', 'left', 'right'], walls, length, depth, others),
      cabinet: () => alongWalls(id, 'cabinet', ['back', 'left', 'right', 'front'], walls, length, depth, others),
      socket: () => connection(id, 'socket', index, counts.socket, walls, length, depth, others),
      network: () => connection(id, 'network', index, counts.network, walls, length, depth, others),
    }[type]();
    placed.set(id, item);
  });

  const items = planned.map(([id]) => ({ ...placed.get(id), tentative: tentative.includes(placed.get(id).type) }));

  return { lights, items };
}
