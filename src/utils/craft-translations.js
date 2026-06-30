import fs from "node:fs";
import path from "node:path";

const TRANSLATION_GROUP = "site";
const DEFAULT_LOCALE = "en";
const TRANSLATION_LOCALES = ["en", "fr", "nl", "de"];

const loadPhpTranslationFile = (locale) => {
  const filePath = path.join(process.cwd(), "cms", "translations", locale, `${TRANSLATION_GROUP}.php`);

  try {
    if (!fs.existsSync(filePath)) {
      return {};
    }

    const source = fs.readFileSync(filePath, "utf8");
    const translations = {};
    const entryPattern = /'((?:\\'|[^'])+)'\s*=>\s*(?:<<<EOT\r?\n([\s\S]*?)\r?\n\s*EOT|'((?:\\'|[^'])*)')\s*,/g;
    let match;

    while ((match = entryPattern.exec(source)) !== null) {
      const [, rawKey, heredocValue, quotedValue] = match;
      const key = rawKey.replace(/\\'/g, "'");
      const value = (heredocValue ?? quotedValue ?? "").replace(/\\'/g, "'").trim();
      translations[key] = value;
    }

    return translations;
  } catch (error) {
    console.warn(`[translations:${locale}] Unable to load Craft translations: ${error.message}`);
    return {};
  }
};

const translations = Object.fromEntries(
  TRANSLATION_LOCALES.map((locale) => [locale, loadPhpTranslationFile(locale)])
);

export const translate = (key, locale = DEFAULT_LOCALE) => {
  const currentTranslations = translations[locale] ?? {};
  const defaultTranslations = translations[DEFAULT_LOCALE] ?? {};

  return currentTranslations[key] ?? defaultTranslations[key] ?? key;
};
