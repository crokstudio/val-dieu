import { fetchCraftEntry } from "../utils/craft-api.js";
import { translateDynamicEntry } from "../utils/dynamic-translations.js";

const FALLBACK_HOMEPAGE = {
  gallery: [
    {
      image: "assets/medias/img/gallery-home_basilique.jpg",
      alt: "gallery image",
    },
    {
      image: "assets/medias/img/gallery-home_parc.jpg",
      alt: "gallery image",
    },
    {
      image: "assets/medias/img/gallery-home_cloche.jpg",
      alt: "gallery image",
    },
    {
      image: "assets/medias/img/gallery-home_cassecroute.jpg",
      alt: "gallery image",
    },
    {
      image: "assets/medias/img/gallery-home_brasserie.jpg",
      alt: "gallery image",
    },
  ],
};

export default async function () {
  return translateDynamicEntry(await fetchCraftEntry("homepage", FALLBACK_HOMEPAGE, "homepage"), "homepage");
}
