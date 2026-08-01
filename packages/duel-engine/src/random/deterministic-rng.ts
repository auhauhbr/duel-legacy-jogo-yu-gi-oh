const FNV_OFFSET_BASIS_32 = 0x811c9dc5;
const FNV_PRIME_32 = 0x01000193;
const NON_ZERO_STATE_FALLBACK = 0x9e3779b9;
const UINT32_RANGE = 0x1_0000_0000;

export interface DeterministicRngState {
  readonly seed: string;
  readonly state: number;
  readonly calls: number;
}

export interface RandomResult<T> {
  readonly value: T;
  readonly nextState: DeterministicRngState;
}

function createState(
  seed: string,
  state: number,
  calls: number,
): DeterministicRngState {
  return Object.freeze({ seed, state: state >>> 0, calls });
}

/**
 * Derivação FNV-1a de 32 bits aplicada aos dois bytes, em ordem little-endian,
 * de cada code unit UTF-16 da seed. Math.imul e as operações bit a bit têm
 * semântica definida pelo ECMAScript, mantendo o resultado entre runtimes.
 */
function hashSeed(seed: string): number {
  let hash = FNV_OFFSET_BASIS_32;

  for (let index = 0; index < seed.length; index += 1) {
    const codeUnit = seed.charCodeAt(index);

    hash ^= codeUnit & 0xff;
    hash = Math.imul(hash, FNV_PRIME_32);
    hash ^= codeUnit >>> 8;
    hash = Math.imul(hash, FNV_PRIME_32);
  }

  const normalizedHash = hash >>> 0;

  return normalizedHash === 0 ? NON_ZERO_STATE_FALLBACK : normalizedHash;
}

export function createDeterministicRng(seed: string): DeterministicRngState {
  if (seed.trim().length === 0) {
    throw new Error("A seed não pode ser vazia.");
  }

  return createState(seed, hashSeed(seed), 0);
}

/**
 * PRNG xorshift32 de George Marsaglia. É adequado para simulação e replay,
 * mas não é criptograficamente seguro. O estado zero é evitado na criação.
 */
export function nextRandomUint32(
  rng: DeterministicRngState,
): RandomResult<number> {
  let value = rng.state >>> 0;

  value ^= value << 13;
  value ^= value >>> 17;
  value ^= value << 5;
  value >>>= 0;

  return Object.freeze({
    value,
    nextState: createState(rng.seed, value, rng.calls + 1),
  });
}

export function nextRandomFloat(
  rng: DeterministicRngState,
): RandomResult<number> {
  const uint32Result = nextRandomUint32(rng);

  return Object.freeze({
    value: uint32Result.value / UINT32_RANGE,
    nextState: uint32Result.nextState,
  });
}

export function nextRandomInt(
  rng: DeterministicRngState,
  minInclusive: number,
  maxExclusive: number,
): RandomResult<number> {
  if (
    !Number.isSafeInteger(minInclusive) ||
    !Number.isSafeInteger(maxExclusive)
  ) {
    throw new Error("Os limites devem ser inteiros seguros.");
  }

  if (minInclusive >= maxExclusive) {
    throw new Error("minInclusive deve ser menor que maxExclusive.");
  }

  const intervalSize = maxExclusive - minInclusive;

  if (intervalSize > UINT32_RANGE) {
    throw new Error("O intervalo não pode exceder o espaço uint32.");
  }

  const uint32Result = nextRandomUint32(rng);
  const offset = Math.floor((uint32Result.value / UINT32_RANGE) * intervalSize);

  return Object.freeze({
    value: minInclusive + offset,
    nextState: uint32Result.nextState,
  });
}
