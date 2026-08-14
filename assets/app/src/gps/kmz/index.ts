import { unzipSync } from 'fflate';
import type { GpsRoute } from '../model';
import { parseKml } from '../kml';

// Une entrée dont la taille non compressée dépasse ce plafond est rejetée avant même d'être
// décompressée (protection zip bomb) — un KML de cette taille n'existe pas en pratique (les
// exports KMZ réels de Google Earth / Google My Maps font quelques centaines de Ko à quelques Mo).
const MAX_ENTRY_SIZE_BYTES = 50 * 1024 * 1024;

export async function parseKmz(content: ArrayBuffer): Promise<GpsRoute> {
    const extracted = unzipSync(new Uint8Array(content), {
        // Le filtre fflate est appelé avant décompression de l'entrée (originalSize vient de
        // l'en-tête ZIP) : on peut donc rejeter une entrée uniquement sur sa taille déclarée,
        // sans jamais l'inflater si elle est suspecte.
        filter(file) {
            if (isUnsafeEntryName(file.name)) {
                return false;
            }
            if (file.originalSize > MAX_ENTRY_SIZE_BYTES) {
                return false;
            }
            // Archives imbriquées (KMZ contenant un .zip/.kmz) : ignorées plutôt que décompressées
            // récursivement — voir documentation/technique/kml-kmz.md.
            return !/\.(zip|kmz)$/i.test(file.name);
        },
    });

    const kmlEntryName = Object.keys(extracted)
        .filter((name) => /\.kml$/i.test(name))
        // Convention quasi universelle : un seul doc.kml à la racine de l'archive. On prend la
        // première entrée .kml trouvée (la plus proche de la racine, puis par ordre alphabétique)
        // plutôt que d'exiger un nom exact, certains exports utilisant un autre nom que "doc.kml".
        .sort((a, b) => a.split('/').length - b.split('/').length || a.localeCompare(b))[0];

    if (undefined === kmlEntryName) {
        throw new Error('parseKmz : aucun fichier .kml exploitable trouvé dans cette archive.');
    }

    return parseKml(new TextDecoder('utf-8').decode(extracted[kmlEntryName]));
}

// Rejette tout chemin absolu ou contenant un segment ".." — défense en profondeur : les octets
// extraits ne sont jamais écrits sur un chemin disque (tout reste en mémoire, transmis à
// parseKml), donc un traversal classique n'a pas de cible exploitable ici. Le garde-fou est gardé
// par hygiène : un nom d'entrée d'archive n'est jamais un input de confiance.
function isUnsafeEntryName(name: string): boolean {
    return name.startsWith('/') || name.split('/').includes('..');
}
