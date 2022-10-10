const path = require('path');
const webpack = require("webpack");
const HtmlWebpackPlugin = require('html-webpack-plugin');
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const CopyPlugin = require("copy-webpack-plugin");

new webpack.ProvidePlugin({
  $: 'jquery',
  jQuery: 'jquery',
});

module.exports = {
  mode: 'production',
  entry: './src/js/main.js',
  output: {
    path: path.resolve(__dirname, 'dist/js'),
    filename: '[name].[contenthash].js',
    clean:true,
  },
  optimization: {
     splitChunks: {
       cacheGroups: {
         vendor: {
           test: /[\\/]node_modules[\\/]/,
           name: 'vendor',
           chunks: 'all',
         },
         styles: {
           test: /[\\/]node_modules[\\/]/,
           name: "vendor",
           type: "css/mini-extract",
           chunks: "all",
           enforce:true
         }
       },
     },
  },
  module: {
	  rules: [
	    {
	      test: /\.css$/,
	      use: [MiniCssExtractPlugin.loader, 'css-loader']
	    }
	  ],
  },
  plugins: [
    new HtmlWebpackPlugin({
      template: path.resolve(__dirname, 'house/index.php'),
      filename: path.resolve(__dirname, 'dist/house/index.php'),
    }),
    new HtmlWebpackPlugin({
      template: path.resolve(__dirname, 'house/register.php'),
      filename: path.resolve(__dirname, 'dist/house/register.php'),
    }),
    new MiniCssExtractPlugin({
    	filename: "[name].[contenthash].css"
    }),
    new CopyPlugin({
    	patterns: [
    		{from: path.resolve(__dirname, 'admin/'), to: path.resolve(__dirname, 'dist/admin')},
    		{from: path.resolve(__dirname, 'classes/'), to: path.resolve(__dirname, 'dist/classes')},
    	],
    }),
  ]
};