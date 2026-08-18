const path = require("path");
const webpackConfig = require("@nextcloud/webpack-vue-config");

webpackConfig.entry = {
  main: path.join(__dirname, "src", "main.js"),
};

const defaultAssetFilename = webpackConfig.output.assetModuleFilename || "[contenthash][ext]";
webpackConfig.output.assetModuleFilename = (pathData) => {
  if (pathData.filename && pathData.filename.endsWith("pdf.worker.min.mjs")) {
    return "superadminpage-pdf.worker.min.js";
  }
  return typeof defaultAssetFilename === "function" ? defaultAssetFilename(pathData) : defaultAssetFilename;
};

module.exports = webpackConfig;
