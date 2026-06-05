import "dotenv/config";

const FALLBACK_INTRO_TEXT =
  "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.";

const getCraftApiUrl = () => {
  const value = process.env.CRAFT_API_URL;
  return value && value.trim() ? value.trim() : "";
};

const readIntroText = (payload) => {
  return (
    payload?.introText ||
    payload?.data?.introText ||
    payload?.data?.attributes?.introText ||
    ""
  );
};

export default async function () {
  const apiUrl = getCraftApiUrl();

  if (!apiUrl) {
    return {
      introText: FALLBACK_INTRO_TEXT,
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

    const payload = await response.json();
    const introText = readIntroText(payload);

    return {
      introText: introText || FALLBACK_INTRO_TEXT,
      source: introText ? "craft" : "fallback",
    };
  } catch (error) {
    console.warn(`[homepage] Using fallback intro text: ${error.message}`);

    return {
      introText: FALLBACK_INTRO_TEXT,
      source: "fallback",
    };
  }
}
