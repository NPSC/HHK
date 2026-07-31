import { defineConfig } from 'vite';
import fs from 'node:fs';
import path from 'node:path';

// Writes public/build/hot while `vite dev` is running, containing the dev
// server's origin. Vite.php checks for this file to decide whether to point
// pages at the dev server (with HMR) or the built manifest.
const hotFile = path.resolve(__dirname, 'public/build/hot');

function hotFilePlugin() {
	return {
		name: 'hhk-hot-file',
		configureServer(server) {
			const removeHotFile = () => {
				if (fs.existsSync(hotFile)) fs.rmSync(hotFile);
			};

			process.on('exit', removeHotFile);
			process.on('SIGINT', () => process.exit());
			process.on('SIGTERM', () => process.exit());

			server.httpServer?.once('listening', () => {
				const address = server.httpServer.address();
				if (typeof address !== 'object' || !address) return;

				const protocol = server.config.server.https ? 'https' : 'http';
				const host = ['::', '0.0.0.0', '::1'].includes(address.address)
					? 'localhost'
					: address.address;

				fs.mkdirSync(path.dirname(hotFile), { recursive: true });
				fs.writeFileSync(hotFile, `${protocol}://${host}:${address.port}`);
			});
		},
		buildStart() {
			if (fs.existsSync(hotFile)) fs.rmSync(hotFile);
		},
	};
}

export default defineConfig({
	plugins: [hotFilePlugin()],
	publicDir: false,
	base: '/build/',
	build: {
		manifest: true,
		outDir: 'public/build',
		emptyOutDir: true,
		rollupOptions: {
			input: {
				// Opt pages in incrementally by adding entries here and calling
				// HHK\Vite\Vite::asset('resources/js/<entry>.js') from the page.
				// house.js/admin.js both import the shared vendor.js and
				// common.js bundles plus their site's jQuery UI theme.
				house: 'resources/js/house.js',
				admin: 'resources/js/admin.js',
			},
			output: {
				manualChunks(id) {
					if (id.includes('/node_modules/') || id.includes('/resources/js/common/jquery.js')) {
						return 'vendor';
					}
				},
			},
		},
	},
	server: {
		host: 'localhost',
		port: 5173,
		strictPort: true,
		cors: true,
	},
});
