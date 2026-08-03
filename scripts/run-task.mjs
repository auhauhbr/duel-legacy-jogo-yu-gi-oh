import { spawnSync } from "node:child_process";

const task = process.argv[2];
const pnpm = process.platform === "win32" ? "pnpm.cmd" : "pnpm";
const composer = process.platform === "win32" ? "composer.bat" : "composer";

function run(command, args, cwd = process.cwd(), env = process.env) {
  const result = spawnSync(command, args, { cwd, env, stdio: "inherit" });

  if (result.error) {
    throw result.error;
  }

  if (result.status !== 0) {
    process.exit(result.status ?? 1);
  }
}

function composerAt(directory, ...args) {
  run(composer, [`--working-dir=${directory}`, ...args], process.cwd(), {
    ...process.env,
    COMPOSER_ROOT_VERSION: "0.0.0",
  });
}

const tasks = {
  build() {
    run(pnpm, ["--filter", "@duel-legacy/web", "build"]);
    for (const directory of [
      "apps/api",
      "packages/duel-engine",
      "packages/bot-engine",
    ]) {
      composerAt(directory, "validate", "--strict");
      composerAt(directory, "dump-autoload", "--strict-psr");
    }
    run("php", ["scripts/check-php-syntax.php"]);
  },
  test() {
    composerAt("packages/duel-engine", "test");
    composerAt("apps/api", "test");
  },
  lint() {
    run(pnpm, ["exec", "eslint", "apps/web", "scripts/run-task.mjs"]);
    composerAt("packages/duel-engine", "analyse");
    composerAt("packages/bot-engine", "analyse");
    composerAt("apps/api", "analyse");
    composerAt("apps/api", "format:check");
  },
  typecheck() {
    run(pnpm, ["--filter", "@duel-legacy/web", "typecheck"]);
    composerAt("packages/duel-engine", "analyse");
    composerAt("packages/bot-engine", "analyse");
    composerAt("apps/api", "analyse");
  },
  "format:check"() {
    run(pnpm, ["exec", "prettier", "--check", "."]);
    composerAt("apps/api", "format:check");
  },
};

if (!task || !(task in tasks)) {
  throw new Error(`Tarefa desconhecida: ${task ?? "ausente"}.`);
}

tasks[task]();
