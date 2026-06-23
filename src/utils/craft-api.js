import "dotenv/config";

const clone = (value) => JSON.parse(JSON.stringify(value));
const LOCAL_CRAFT_HOSTS = new Set(["localhost", "127.0.0.1", "::1"]);

const readCraftPayload = (payload) => {
  if (payload?.data?.attributes) {
    return payload.data.attributes;
  }

  if (payload?.data && !Array.isArray(payload.data)) {
    return payload.data;
  }

  return payload;
};

const isLocalCraftUrl = (value) => {
  try {
    return LOCAL_CRAFT_HOSTS.has(new URL(value).hostname);
  } catch {
    return false;
  }
};

const normalizeCraftAssetUrl = (value) => {
  if (typeof value !== "string" || !/^https?:\/\//.test(value) || !isLocalCraftUrl(value)) {
    return value;
  }

  const url = new URL(value);

  if (!url.pathname.startsWith("/uploads/")) {
    return value;
  }

  return `${url.pathname}${url.search}${url.hash}`;
};

const normalizeCraftAssetUrls = (value) => {
  if (Array.isArray(value)) {
    return value.map(normalizeCraftAssetUrls);
  }

  if (value && typeof value === "object") {
    return Object.fromEntries(
      Object.entries(value).map(([key, entryValue]) => [key, normalizeCraftAssetUrls(entryValue)])
    );
  }

  return normalizeCraftAssetUrl(value);
};

export const getCraftEndpointUrl = (endpoint) => {
  const configuredUrl = process.env.CRAFT_API_URL?.trim();

  if (!configuredUrl) {
    return "";
  }

  const endpointName = endpoint.replace(/^\/?api\//, "").replace(/\.json$/, "");
  const endpointPath = `/api/${endpointName}.json`;
  const cleanUrl = configuredUrl.replace(/\/$/, "");

  if (/\/api\/[^/]+\.json$/.test(cleanUrl)) {
    return cleanUrl.replace(/\/api\/[^/]+\.json$/, endpointPath);
  }

  if (/\/api$/.test(cleanUrl)) {
    return `${cleanUrl}/${endpointName}.json`;
  }

  return `${cleanUrl}${endpointPath}`;
};

export const fetchCraftEntry = async (endpoint, fallback, label = endpoint) => {
  const apiUrl = getCraftEndpointUrl(endpoint);
  const fallbackData = clone(fallback);

  if (!apiUrl) {
    return {
      ...fallbackData,
      source: "fallback",
    };
  }

  try {
    const response = await fetch(apiUrl, {
      headers: {
        Accept: "application/json",
      },
    });

    if (!response.ok) {
      throw new Error(`Craft API returned ${response.status}`);
    }

    const payload = readCraftPayload(await response.json());

    if (!payload || typeof payload !== "object" || Array.isArray(payload)) {
      return {
        ...fallbackData,
        source: "fallback",
      };
    }

    return {
      ...fallbackData,
      ...normalizeCraftAssetUrls(payload),
      source: "craft",
    };
  } catch (error) {
    console.warn(`[${label}] Using fallback content: ${error.message}`);

    return {
      ...fallbackData,
      source: "fallback",
    };
  }
};
