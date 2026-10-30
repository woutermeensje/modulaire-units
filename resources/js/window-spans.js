/**
 * How a run of neighbouring window slots is split into windows: two slots each, the last one three when the run is
 * odd, so a run of three is one window centred on its middle slot. Matches App\Support\UnitConfiguration::windowSpans().
 *
 * @param {number} length
 * @returns {number[]}
 */
export function windowSpans(length) {
  if (length < 2) {
    return [];
  }

  const spans = Array(Math.floor(length / 2)).fill(2);
  if (length % 2 === 1) {
    spans[spans.length - 1] = 3;
  }

  return spans;
}
