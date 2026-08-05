import { defineConfig, lazyPlugins } from "vite-plus";
import fs from "node:fs";
import path from "node:path";

// Writes public/build/hot while `vite dev` is running, containing the dev
// server's origin. Vite.php checks for this file to decide whether to point
// pages at the dev server (with HMR) or the built manifest.
const hotFile = path.resolve(__dirname, "public/build/hot");

function hotFilePlugin() {
  return {
    name: "hhk-hot-file",
    configureServer(server) {
      const removeHotFile = () => {
        if (fs.existsSync(hotFile)) fs.rmSync(hotFile);
      };

      process.on("exit", removeHotFile);
      process.on("SIGINT", () => process.exit());
      process.on("SIGTERM", () => process.exit());

      server.httpServer?.once("listening", () => {
        const address = server.httpServer.address();
        if (typeof address !== "object" || !address) return;

        const protocol = server.config.server.https ? "https" : "http";
        const host = ["::", "0.0.0.0", "::1"].includes(address.address)
          ? "localhost"
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
  staged: {
    "*": "vp check --fix",
  },
  fmt: {
    ignorePatterns: [
      "vendor/**",
      "public/debugbar-assets/**", // symlink into vendor/php-debugbar
      "public/build/**",
      "**/*.min.js",
      "**/*-min.js",
      "**/*.min.css",
      "package-lock.json",
      ".vscode/**",
      "resources/css/toastr.css",
      "**/*.htm", // legacy non-UTF8 PHP-side docs, not web assets
    ],
  },
  lint: {
    plugins: ["oxc", "typescript", "unicorn"], // no React in this codebase
    categories: {
      correctness: "warn",
    },
    env: {
      builtin: true,
    },
    ignorePatterns: [
      "vendor/**",
      "public/debugbar-assets/**", // symlink into vendor/php-debugbar
      "public/build/**",
      "**/*.min.js",
      "**/*-min.js",
      "**/*.min.css",
      "package-lock.json",
      ".vscode/**",
      "resources/css/toastr.css",
    ],
    overrides: [
      {
        files: ["./resources/**/*.{js,mjs,cjs}"],
        rules: {
          "constructor-super": "error",
          "for-direction": "error",
          "getter-return": "error",
          "no-async-promise-executor": "error",
          "no-case-declarations": "error",
          "no-class-assign": "error",
          "no-compare-neg-zero": "error",
          "no-cond-assign": "error",
          "no-const-assign": "error",
          "no-constant-binary-expression": "error",
          "no-constant-condition": "error",
          "no-control-regex": "error",
          "no-debugger": "error",
          "no-delete-var": "error",
          "no-dupe-class-members": "error",
          "no-dupe-else-if": "error",
          "no-dupe-keys": "error",
          "no-duplicate-case": "error",
          "no-empty": "error",
          "no-empty-character-class": "error",
          "no-empty-pattern": "error",
          "no-empty-static-block": "error",
          "no-ex-assign": "error",
          "no-extra-boolean-cast": "error",
          "no-fallthrough": "error",
          "no-func-assign": "error",
          "no-global-assign": "error",
          "no-import-assign": "error",
          "no-invalid-regexp": "error",
          "no-irregular-whitespace": "error",
          "no-loss-of-precision": "error",
          "no-misleading-character-class": "error",
          "no-new-native-nonconstructor": "error",
          "no-nonoctal-decimal-escape": "error",
          "no-obj-calls": "error",
          "no-prototype-builtins": "error",
          "no-redeclare": "error",
          "no-regex-spaces": "error",
          "no-self-assign": "error",
          "no-setter-return": "error",
          "no-shadow-restricted-names": "error",
          "no-sparse-arrays": "error",
          "no-this-before-super": "error",
          "no-unassigned-vars": "error",
          "no-undef": "error",
          "no-unexpected-multiline": "error",
          "no-unreachable": "error",
          "no-unsafe-finally": "error",
          "no-unsafe-negation": "error",
          "no-unsafe-optional-chaining": "error",
          "no-unused-labels": "error",
          "no-unused-private-class-members": "error",
          "no-unused-vars": "error",
          "no-useless-assignment": "error",
          "no-useless-backreference": "error",
          "no-useless-catch": "error",
          "no-useless-escape": "error",
          "no-with": "error",
          "preserve-caught-error": "error",
          "require-yield": "error",
          "use-isnan": "error",
          "valid-typeof": "error",
        },
        env: {
          browser: true,
          jquery: true,
        },
        globals: {
          moment: "readonly",
          he: "readonly",
          DOMPurify: "readonly",
          buffer: "readonly",
          libphonenumber: "readonly",
          DataTable: "readonly",
          flagAlertMessage: "readonly",
          dateRender: "readonly",
          dayRender: "readonly",
          hhkReportError: "readonly",
          isIE: "readonly",
          openiframe: "readonly",
          logoutTimer: "readonly",
          getDialogWidth: "readonly",
          createAutoComplete: "readonly", // from public/js/createAutoComplete.js, loaded alongside these bundles
          createRoleAutoComplete: "readonly",
          createZipAutoComplete: "readonly",
          dateFormat: "readonly", // page controller script reads #dateFormat into this, e.g. public/admin/js/Configure.js
          // public/house/js/payments.js, loaded alongside visitDialog.js on CheckingIn.php/Reserve.php/VisitInterval.php,
          // and alongside regForm.js on ShowRegForm.php
          showReceipt: "readonly",
          setupPayments: "readonly",
          paymentRedirect: "readonly",
          verifyAmtTendrd: "readonly",
          verifyBalDisp: "readonly",
          amtPaid: "readonly",
          getApplyDiscDiag: "readonly",
          paymentsTable: "readonly",
          reprintReceipt: "readonly",
          setupCOF: "readonly",
          cardOnFile: "readonly",
          // public/house/js/invoice.js, loaded alongside resources/js/register.js on register.php
          invLoadPc: "readonly",
          invSetBill: "readonly",
          invoiceAction: "readonly",
          // set by public/house/js/checkingIn.js, reserve.js, resources/js/register.js when present on the page
          pageManager: "readonly",
          calendar: "readonly",
          fixedRate: "readonly",
          refreshdTables: "readonly",
          getIncomeDiag: "readonly", // public/house/js/resv.js
          // public/house/js/resv.js, loaded alongside regForm.js on ShowRegForm.php
          getRegistrationDialog: "readonly",
          showRegDialog: "readonly",
          // public/house/js/resvManager.js and resv.js, loaded alongside hospitalStay.js
          verifyDocAgent: "readonly",
          getAgent: "readonly",
          getDoc: "readonly",
          // resources/js/house.js exposes these from house/visitDialog.js; consumed by resources/js/register.js
          viewVisit: "readonly",
          saveFees: "readonly",
          // public/house/js/SigWebTablet.js, loaded alongside regForm.js on ShowRegForm.php when a signature pad is required
          IsSigWebInstalled: "readonly",
          GetSigWebVersion: "readonly",
          ClearTablet: "readonly",
          SetDisplayXSize: "readonly",
          SetDisplayYSize: "readonly",
          SetTabletState: "readonly",
          SetJustifyMode: "readonly",
          GetTabletState: "readonly",
          NumberOfTabletPoints: "readonly",
          Reset: "readonly",
        },
      },
      {
        files: ["vite.config.js"],
        env: {
          node: true,
        },
      },
      {
        files: ["**/*.json"],
        rules: {},
        jsPlugins: [],
      },
      {
        files: ["**/*.css"],
        rules: {},
        jsPlugins: [],
      },
    ],
    options: {
      typeAware: true,
      typeCheck: true,
    },
    jsPlugins: [
      {
        name: "vite-plus",
        specifier: "vite-plus/oxlint-plugin",
      },
    ],
    rules: {
      "vite-plus/prefer-vite-plus-imports": "error",
    },
  },
  plugins: lazyPlugins(() => [hotFilePlugin()]),
  publicDir: false,
  base: "/build/",
  build: {
    manifest: true,
    outDir: "public/build",
    emptyOutDir: true,
    rollupOptions: {
      input: {
        // Opt pages in incrementally by adding entries here and calling
        // HHK\Vite\Vite::asset('resources/js/<entry>.js') from the page.
        // house.js/admin.js both import the shared vendor.js and
        // common.js bundles plus their site's jQuery UI theme.
        house: "resources/js/house.js",
        register: "resources/js/house/register.js",
        guestLoad: "resources/js/house/guestload.js",
        // self-contained: imports vendor.js/common.js itself instead of relying on house.js,
        // so ShowRegForm.php loads this alone rather than alongside house.js
        regForm: "resources/js/house/regForm.js",
        admin: "resources/js/admin.js",
        nameEdit: "resources/js/admin/nameEdit.js",
        root: "resources/js/root.js",
        loginHouse: "resources/js/login/house.js",
        loginAdmin: "resources/js/login/admin.js",
      },
      output: {
        manualChunks(id) {
          if (id.includes("/node_modules/") || id.includes("/resources/js/common/jquery.js")) {
            return "vendor";
          }
        },
      },
    },
  },
  server: {
    host: "localhost",
    port: 5173,
    strictPort: true,
    cors: true,
  },
});
