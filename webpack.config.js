const path = require('path');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CssMinimizerWebpackPlugin = require("css-minimizer-webpack-plugin");
const TerserPlugin = require("terser-webpack-plugin");

module.exports = {
    mode: 'production',
    entry: {
        admin: "./src/js/admin.js",
        house: "./src/js/house.js",
        vendor: "./src/js/vendor.js"
    },
    module: {
        rules: [
            {
                test: /\.(s(a|c)ss)$/,
                use: [MiniCssExtractPlugin.loader,'css-loader', 'sass-loader']
            }
        ]
    },
    output: {
        filename: '[name].bundle.js',
        path: path.resolve(__dirname, 'js'),
    },
    optimization: {
        minimizer: [new CssMinimizerWebpackPlugin(), new TerserPlugin()],
    },
    plugins: [new CleanWebpackPlugin(), new MiniCssExtractPlugin({filename: "../css/[name].css"})]
}