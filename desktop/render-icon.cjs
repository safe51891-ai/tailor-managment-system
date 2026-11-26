'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { Resvg } = require('@resvg/resvg-js');
const build = path.join(__dirname, 'build');
const png = new Resvg(fs.readFileSync(path.join(build, 'icon.svg')), { font: { loadSystemFonts: true, defaultFontFamily: 'Georgia' } }).render().asPng();
fs.writeFileSync(path.join(build, 'icon.png'), png);
// Windows supports PNG-encoded icon entries (zero size bytes mean 256 px).
const header = Buffer.alloc(22);
header.writeUInt16LE(1, 2); header.writeUInt16LE(1, 4);
header.writeUInt16LE(1, 10); header.writeUInt16LE(32, 12);
header.writeUInt32LE(png.length, 14); header.writeUInt32LE(22, 18);
fs.writeFileSync(path.join(build, 'icon.ico'), Buffer.concat([header, png]));
