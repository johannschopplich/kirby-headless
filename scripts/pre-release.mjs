import * as fsp from "node:fs/promises";
import { x } from "tinyexec";

const { version } = JSON.parse(await fsp.readFile("package.json", "utf8"));
const composer = JSON.parse(await fsp.readFile("composer.json", "utf8"));
composer.version = version;
await fsp.writeFile(
  "composer.json",
  `${JSON.stringify(composer, undefined, 2)}\n`,
);

await x("composer", ["update"], {
  throwOnError: true,
  nodeOptions: { stdio: "inherit" },
});
