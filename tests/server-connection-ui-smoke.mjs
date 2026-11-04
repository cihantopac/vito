import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const root = new URL('..', import.meta.url);
const read = (path) => readFileSync(new URL(path, root), 'utf8');

const createServer = read('resources/js/pages/servers/create.tsx');
const userMenu = read('resources/js/components/user-menu-content.tsx');

assert.match(createServer, /ssh_user:\s*string/, 'Existing Server must submit the chosen SSH user.');
assert.match(createServer, /form\.errors\.provider/, 'Connection failures must be shown to the user.');
assert.match(createServer, /'provider'.*'ssh_user'/s, 'Connection failures and SSH user validation must return to step one.');
assert.match(userMenu, /href=\{"\/settings\/profile"\}/, 'User Settings must open the profile and password screen.');

console.log('PASS: existing-server SSH user, visible connection errors, and profile settings navigation.');
