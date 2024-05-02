import { $, fs } from "zx";

const { version } = await fs.readJson("./package.json");
const composer = await fs.readJson("./composer.json");
composer.version = version;
await fs.writeJson("./composer.json", composer, { spaces: 2 });
await $`composer update`;
