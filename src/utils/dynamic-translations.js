import "dotenv/config";
import crypto from "node:crypto";
import fs from "node:fs";
import path from "node:path";
import locales from "../_data/locales.js";

const SOURCE_LOCALE = "fr";
const SOURCE_LANG = "FR";
const CACHE_PATH = path.join(process.cwd(), ".cache", "deepl-translations.json");
const TRANSLATABLE_KEYS = new Set(["alt", "day", "subtitle", "text", "title"]);
const MAX_TEXTS_PER_REQUEST = 40;
const DEEPL_TARGET_LANGS = {
  de: "DE",
  en: "EN",
  nl: "NL",
};
let warnedMissingApiKey = false;

const clone = (value) => JSON.parse(JSON.stringify(value));

const readCache = () => {
  try {
    return JSON.parse(fs.readFileSync(CACHE_PATH, "utf8"));
  } catch {
    return {};
  }
};

const writeCache = (cache) => {
  fs.mkdirSync(path.dirname(CACHE_PATH), { recursive: true });
  fs.writeFileSync(CACHE_PATH, `${JSON.stringify(cache, null, 2)}\n`);
};

const hashText = (text) => crypto.createHash("sha256").update(text).digest("hex");

const getDeepLHost = () => {
  if (process.env.DEEPL_API_HOST?.trim()) {
    return process.env.DEEPL_API_HOST.trim().replace(/\/$/, "");
  }

  return process.env.DEEPL_API_KEY?.endsWith(":fx") ? "https://api-free.deepl.com" : "https://api.deepl.com";
};

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const getDeepLHosts = () => {
  const primaryHost = getDeepLHost();

  if (process.env.DEEPL_API_KEY?.endsWith(":fx")) {
    return ["https://api-free.deepl.com"];
  }

  const fallbackHost = primaryHost.includes("api-free.deepl.com")
    ? "https://api.deepl.com"
    : "https://api-free.deepl.com";

  return [primaryHost, fallbackHost];
};

const getCacheKey = (text, targetLang) => `${SOURCE_LANG}:${targetLang}:${hashText(text)}`;

const translateTextBatch = async (texts, targetLang) => {
  const body = new URLSearchParams({
    source_lang: SOURCE_LANG,
    target_lang: targetLang,
    tag_handling: "html",
  });

  for (const text of texts) {
    body.append("text", text);
  }

  let response;

  for (const host of getDeepLHosts()) {
    for (let attempt = 0; attempt < 3; attempt += 1) {
      response = await fetch(`${host}/v2/translate`, {
        method: "POST",
        headers: {
          Authorization: `DeepL-Auth-Key ${process.env.DEEPL_API_KEY}`,
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body,
      });

      if (response.status !== 429) {
        break;
      }

      await wait(1000 * (attempt + 1));
    }

    if (response.status !== 403) {
      break;
    }
  }

  if (!response.ok) {
    const errorText = await response.text();
    throw new Error(`DeepL returned ${response.status}${errorText ? `: ${errorText}` : ""}`);
  }

  const payload = await response.json();
  return payload.translations?.map((translation) => translation.text) || texts;
};

const collectTranslatableRefs = (value, refs, key = "", parent = null, prop = null) => {
  if (Array.isArray(value)) {
    for (const item of value) {
      collectTranslatableRefs(item, refs, key);
    }

    return;
  }

  if (value && typeof value === "object") {
    for (const [entryKey, entryValue] of Object.entries(value)) {
      collectTranslatableRefs(entryValue, refs, entryKey, value, entryKey);
    }

    return;
  }

  if (typeof value === "string" && TRANSLATABLE_KEYS.has(key) && value.trim() && parent && prop) {
    refs.push({ parent, prop, text: value });
  }
};

const translateEntry = async (entry, targetLocale, cache) => {
  if (targetLocale === SOURCE_LOCALE) {
    return entry;
  }

  const targetLang = DEEPL_TARGET_LANGS[targetLocale];

  if (!targetLang) {
    return entry;
  }

  const refs = [];
  collectTranslatableRefs(entry, refs);

  const missingTexts = [];
  const missingTextKeys = new Set();

  for (const ref of refs) {
    const cacheKey = getCacheKey(ref.text, targetLang);

    if (cache[cacheKey]) {
      ref.parent[ref.prop] = cache[cacheKey];
    } else if (!missingTextKeys.has(cacheKey)) {
      missingTextKeys.add(cacheKey);
      missingTexts.push(ref.text);
    }
  }

  if (!missingTexts.length) {
    return entry;
  }

  if (!process.env.DEEPL_API_KEY) {
    if (!warnedMissingApiKey) {
      console.warn("[translate] DEEPL_API_KEY is missing; dynamic Craft content will use French source text.");
      warnedMissingApiKey = true;
    }

    return entry;
  }

  try {
    for (let index = 0; index < missingTexts.length; index += MAX_TEXTS_PER_REQUEST) {
      const textChunk = missingTexts.slice(index, index + MAX_TEXTS_PER_REQUEST);
      const translatedChunk = await translateTextBatch(textChunk, targetLang);

      textChunk.forEach((sourceText, chunkIndex) => {
        cache[getCacheKey(sourceText, targetLang)] = translatedChunk[chunkIndex] || sourceText;
      });
    }
  } catch (error) {
    console.warn(`[translate:${targetLocale}] Using French source text for uncached dynamic content: ${error.message}`);
  }

  for (const ref of refs) {
    const cachedTranslation = cache[getCacheKey(ref.text, targetLang)];

    if (cachedTranslation) {
      ref.parent[ref.prop] = cachedTranslation;
    }
  }

  return entry;
};

export const translateDynamicEntry = async (sourceEntry, label = "craft") => {
  const cache = readCache();
  let cacheChanged = false;
  const originalCacheKeys = new Set(Object.keys(cache));

  const entries = [];

  for (const locale of locales) {
    if (locale.code === SOURCE_LOCALE) {
      entries.push([locale.code, clone(sourceEntry)]);
      continue;
    }

    try {
      entries.push([locale.code, await translateEntry(clone(sourceEntry), locale.code, cache)]);
    } catch (error) {
      console.warn(`[translate:${label}:${locale.code}] Using French source content: ${error.message}`);
      entries.push([locale.code, clone(sourceEntry)]);
    }
  }

  for (const key of Object.keys(cache)) {
    if (!originalCacheKeys.has(key)) {
      cacheChanged = true;
      break;
    }
  }

  if (cacheChanged) {
    writeCache(cache);
  }

  return Object.fromEntries(entries);
};
