// eslint.config.js
const { defineConfig } = require("eslint/config");

module.exports = defineConfig([
    {
        ignores: ["!/tools/linters/.eslint.config.js", "!/tools/linters/.stylelintrc.json", "/public/assets/components/jquery-ui.min.js"],
        languageOptions: {
            ecmaVersion: 2015,
            sourceType: "module"
        },
        files: [
            "**/*.js",
        ],
        rules: {
            semi: "error",
            "prefer-const": "error"
        }
    }
]);
