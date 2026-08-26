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
      "resources/js/common/SigWebTablet.js", // vendored Topaz SigWeb SDK; not written for strict/module linting
      "resources/js/common/stateCountry.js", // vendored BFH Countries/States jQuery plugin
      "resources/js/common/jquery.PrintArea.js", // vendored jQuery Print Area plugin
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
          buffer: "readonly",
          InstaMed: "readonly", // InstaMed payment gateway embed script, INS_EMBED_JS
          HostedForm: "readonly", // Deluxe payment gateway embed script, DELUXE_EMBED_JS / DELUXE_SANDBOX_EMBED_JS
          grecaptcha: "readonly", // Google reCAPTCHA embed script
          referralFormVars: "readonly", // page controller script, public/house/showReferral.php
          isCheckedOut: "readonly", // set by resources/js/house/visitDialog.js, consumed by payments.js and invoice.js
          flagAlertMessage: "readonly",
          dateRender: "readonly",
          hhkReportError: "readonly",
          getDialogWidth: "readonly",
          verifyAddrs: "readonly", // resources/js/common.js exposes this from common/addrPrefs.js
          dateFormat: "readonly", // page controller script reads #dateFormat into this, e.g. public/admin/js/Configure.js
          labels: "readonly", // page controller script reads window.labels into this, e.g. public/house/RoomStatus.php
          startYear: "readonly", // public/house/RoomStatus.php reads window.startYear into this, used by housekeeping.js
          // resources/js/house/payments.js, loaded alongside visitDialog.js on CheckingIn.php/Reserve.php/VisitInterval.php,
          // and alongside regForm.js on ShowRegForm.php
          showReceipt: "readonly",
          paymentRedirect: "readonly",
          verifyAmtTendrd: "readonly",
          verifyBalDisp: "readonly",
          amtPaid: "readonly",
          getApplyDiscDiag: "readonly",
          paymentsTable: "readonly",
          reprintReceipt: "readonly",
          cardOnFile: "readonly",
          daysCalculator: "readonly", // resources/js/house/payments.js, used by resv.js
          // resources/js/house/invoice.js, loaded alongside resources/js/house/register.js on register.php
          invLoadPc: "readonly",
          invSetBill: "readonly",
          invoiceAction: "readonly",
          // set by resources/js/house/checkingIn.js, reserve.js, register.js when present on the page
          pageManager: "readonly",
          calendar: "readonly",
          fixedRate: "readonly",
          refreshdTables: "readonly",
          getIncomeDiag: "readonly", // resources/js/house/resv.js
          // resources/js/house/resv.js, loaded alongside regForm.js on ShowRegForm.php
          getRegistrationDialog: "readonly",
          showRegDialog: "readonly",
          // resources/js/house/resv.js, loaded alongside resources/js/house/resvManager.js
          getAgent: "readonly",
          getDoc: "readonly",
          setupRates: "readonly",
          jsonFetch: "readonly", // resources/js/house/resv.js, used by checkingIn.js and reserve.js
          // set by resources/js/house/reserve.js, checkingIn.js, loaded alongside resources/js/house/resvManager.js
          payFailPage: "readonly",
          // resources/js/house.js exposes these from house/visitDialog.js; consumed by resources/js/register.js
          viewVisit: "readonly",
          saveFees: "readonly",
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
        house: "resources/js/house.js",
        housekeeping: "resources/js/house/housekeeping.js",
        checkin: "resources/js/house/checkin.js",
        checkingin: "resources/js/house/checkingIn.js",
        missingdemog: "resources/js/house/missingDemog.js",
        register: "resources/js/house/register.js",
        resvManager: "resources/js/house/resvManager.js",
        guestLoad: "resources/js/house/guestload.js",
        regForm: "resources/js/house/regForm.js",
        referralform: "resources/js/house/referralform.js",
        rescBuilder: "resources/js/house/rescBuilder.js",
        resv: "resources/js/house/resv.js",
        reserve: "resources/js/house/reserve.js",
        payments: "resources/js/house/payments.js",
        invoice: "resources/js/house/invoice.js",
        guestReferral: "resources/js/house/guestReferral.js",
        guestTransfer: "resources/js/house/guestTransfer.js",
        guestView: "resources/js/house/guestView.js",
        visitInterval: "resources/js/house/visitInterval.js",
        statement: "resources/js/house/statement.js",
        admin: "resources/js/admin.js",
        accessLog: "resources/js/admin/accessLog.js",
        configure: "resources/js/admin/configure.js",
        duplicteMerger: "resources/js/admin/duplicateMerger.js",
        misc: "resources/js/admin/misc.js",
        nameEdit: "resources/js/admin/nameEdit.js",
        nameSch: "resources/js/admin/nameSch.js",
        root: "resources/js/root.js",
        login: "resources/js/login.js",
        // Server-side CSS bundles for generated PDFs
        receiptCss: "resources/css/pdf/receipt.css",
        statementCss: "resources/css/pdf/statement.css",
        invoiceCss: "resources/css/pdf/invoice.css",
      },
      output: {
        manualChunks(id) {
          //split bootstrap
          if (id.includes("/node_modules/bootstrap/dist/css/bootstrap.min.css")) {
            return "bootstrap-full";
          }
          // pack all vendor modules together
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
