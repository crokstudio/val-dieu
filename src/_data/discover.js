import { fetchCraftEntry } from "../utils/craft-api.js";
import { translateDynamicEntry } from "../utils/dynamic-translations.js";

const FALLBACK_DISCOVER = {
  timeline: [
    {
      date: "530",
      title: "La règle de saint Benoît",
      text: "Ora et Labora, prie et travaille. On résume ainsi la règle écrite par Benoît de Nursie vers 530 au mont Cassin. C’est la règle de vie monastique la plus suivie aujourd’hui.",
      image: "assets/medias/img/timeline_530.jpg",
      alt: "",
    },
    {
      date: "1098",
      title: "Fondation de l’abbaye de Cîteaux (Bourgogne)",
      text: "En 1098, le moine bénédictin Robert de Molesmes fonde un nouveau monastère en Bourgogne : l’abbaye de Cîteaux. Il souhaite réformer la vie monastique en encourageant un retour strict à la Règle de saint Benoît : pauvreté, simplicité, travail manuel.",
      image: "assets/medias/img/timeline_1098.png",
      alt: "",
    },
    {
      date: "1115",
      title: "De Cîteaux au Val-Dieu",
      text: "Au 12e siècle, le modèle de vie monacale des cisterciens connait une expansion fulgurante. Plus de 700 abbayes masculines sont fondées à travers l’Europe. Elles sont installées dans des vallées isolées, à proximité de l’eau. Leurs plans sont toujours similaires et rassemblent toutes les fonctions utiles aux moines : réfectoire, dortoir, jardin, caves, bibliothèque, zones d’artisanat et agricoles (forge, moulin, brasserie, etc.) <br> Ainsi, un groupe de moines guidés par Bernard fonde l’abbaye de Clairvaux en 1115. Bernard de Clairvaux aura un grand rayonnement à travers ses écrits et sa vie. Il participe à la fondation de 68 abbayes. Parmi elles, l’abbaye d’Eberbach près de Mayence en Allemagne. Vers 1180, Eberbach envoie un groupe de moines fonder l’abbaye de Hocht (Lanaken, nord de Maastricht). Ces moines cisterciens quittent quelques années plus tard Hocht pour s’installer définitivement au Val-Dieu.",
      image: "assets/medias/img/timeline_1115.png",
      alt: "",
    },
  ],
};

export default async function () {
  return translateDynamicEntry(await fetchCraftEntry("discover", FALLBACK_DISCOVER, "discover"), "discover");
}
