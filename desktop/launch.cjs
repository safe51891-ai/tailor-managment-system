'use strict';
const { spawn } = require('node:child_process');
const env = { ...process.env };
// Editors based on Electron can export this flag to their terminal children.
delete env.ELECTRON_RUN_AS_NODE;
const child = spawn(require('electron'), [__dirname], { cwd: __dirname, env, stdio: 'inherit' });
child.on('error', error => { console.error(error.message); process.exitCode = 1; });
child.on('exit', code => { process.exitCode = code || 0; });
process.on('SIGINT', () => child.kill('SIGINT'));
process.on('SIGTERM', () => child.kill());
