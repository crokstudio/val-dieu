// import dotenv
import "dotenv/config";
//import products from "./src/_data/products.js"; // Use ES module import
import slugify from "slugify";
//for image optimization
import Image from "@11ty/eleventy-img"; 
//node path module
import path from "path";

const RESPONSIVE_IMAGE_WIDTHS = [320, 480, 768, 1024, 1440, 1920];
const BACKGROUND_IMAGE_WIDTHS = [640, 1280];
const RESPONSIVE_IMAGE_URL_PATH = "/assets/medias/img/optimized/";
const RESPONSIVE_IMAGE_OUTPUT_DIR = "./dist/assets/medias/img/optimized/";
const RESPONSIVE_IMAGE_ENCODER_OPTIONS = {
  sharpWebpOptions: {
    quality: 90,
    alphaQuality: 100,
    smartSubsample: true,
  },
  sharpJpegOptions: {
    quality: 90,
    chromaSubsampling: "4:4:4",
    mozjpeg: true,
  },
};

const isRemoteImage = (src) => /^https?:\/\//.test(src);
const getImageExtension = (src) => path.extname(src || "").toLowerCase();
const isSvgImage = (src) => getImageExtension(src) === ".svg";

const getImageSourcePath = (src) => {
  if (isRemoteImage(src)) {
    return src;
  }

  return path.join(process.cwd(), "src", String(src).replace(/^\//, ""));
};

const getResponsiveFormats = (src, { background = false } = {}) => {
  if (background) {
    return ["auto"];
  }

  const extension = getImageExtension(src);

  if (extension === ".svg" || extension === ".webp" || extension === ".avif") {
    return ["auto"];
  }

  return ["webp", "auto"];
};

const escapeAttribute = (value) => String(value).replace(/&/g, "&amp;").replace(/"/g, "&quot;");
const normalizeAttributeValue = (value, fallback) => {
  if (value === undefined || value === null) {
    return fallback;
  }

  const normalizedValue = String(value).trim();
  return normalizedValue || fallback;
};

const renderPlainImageTag = (src, attributes = {}) => {
  const htmlAttributes = Object.entries(attributes)
    .filter(([, value]) => value !== undefined && value !== null && value !== "")
    .map(([key, value]) => `${key}="${escapeAttribute(value)}"`)
    .join(" ");

  return `<img src="${escapeAttribute(src)}"${htmlAttributes ? ` ${htmlAttributes}` : ""}>`;
};

const generateResponsiveMetadata = async (src, widths, options = {}) => {
  return Image(getImageSourcePath(src), {
    widths,
    formats: getResponsiveFormats(src, options),
    urlPath: RESPONSIVE_IMAGE_URL_PATH,
    outputDir: RESPONSIVE_IMAGE_OUTPUT_DIR,
    ...RESPONSIVE_IMAGE_ENCODER_OPTIONS,
  });
};

export default async function (eleventyConfig) {

  eleventyConfig.addNunjucksAsyncShortcode("responsiveImage", async (src, alt = "", sizes = "100vw", className = "", loading = "lazy", decoding = "async", fetchpriority = "") => {
    if (!src) {
      return "";
    }

    const resolvedLoading = normalizeAttributeValue(loading, "lazy");
    const resolvedDecoding = normalizeAttributeValue(decoding, "async");
    const imageAttributes = {
      alt,
      sizes,
      loading: resolvedLoading,
      decoding: resolvedDecoding,
      class: className,
    };

    if (fetchpriority) {
      imageAttributes.fetchpriority = fetchpriority;
    }

    if (isSvgImage(src)) {
      return renderPlainImageTag(src, {
        alt,
        loading: resolvedLoading,
        decoding: resolvedDecoding,
        class: className,
        fetchpriority,
      });
    }

    const metadata = await generateResponsiveMetadata(src, RESPONSIVE_IMAGE_WIDTHS);
    return Image.generateHTML(metadata, imageAttributes);
  });

  eleventyConfig.addNunjucksAsyncShortcode("responsiveBackground", async (src) => {
    if (!src) {
      return "";
    }

    if (isSvgImage(src)) {
      return `url('${src}')`;
    }

    const metadata = await generateResponsiveMetadata(src, BACKGROUND_IMAGE_WIDTHS, { background: true });
    const [format] = Object.keys(metadata);
    const sources = metadata[format];

    if (!sources.length) {
      return `url('${src}')`;
    }

    if (sources.length === 1) {
      return `url('${sources[0].url}')`;
    }

    return `image-set(url('${sources[0].url}') 1x, url('${sources[sources.length - 1].url}') 2x)`;
  });

  // avoid processing and watching files
  eleventyConfig.ignores.add("./src/assets/**/*");
  eleventyConfig.watchIgnores.add("./src/assets/**/*");

  // make sure files are physically copied with --serve
  eleventyConfig.setServerPassthroughCopyBehavior("copy");

  // copy files
  eleventyConfig.addPassthroughCopy("./src/assets/fonts");
  //eleventyConfig.addPassthroughCopy("./src/assets/medias"); //-- we don't want to copy all medias unoptimized
  eleventyConfig.addPassthroughCopy("./src/assets/medias/video");
  eleventyConfig.addPassthroughCopy("./src/assets/medias/svg");
  eleventyConfig.addPassthroughCopy("./src/assets/medias/img");
  eleventyConfig.addPassthroughCopy("./src/assets/medias/icons");
  eleventyConfig.addPassthroughCopy({ "./src/assets/medias/pdf": "assets/medias/pdf" });
  
  // Eleventy dev server config
  eleventyConfig.setServerOptions({
    port: 3000,
    watch: ["./dist/assets/css/**/*.css", "./dist/assets/js/**/*.js"],
  });
}

export const config = {
  dir: {
    input: "src",
    output: "dist",
  },
};
