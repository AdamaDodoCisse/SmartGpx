<?php

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// La grille de crédits (credit_pack) n'est plus peuplée par une migration (voir
// doctrine.yaml when@test : schéma géré via `doctrine:schema:update`, qui ne rejoue aucune
// donnée) — on la seed ici une fois par run de suite, avant le premier test, en invoquant la
// vraie commande (idempotente) plutôt que de dupliquer sa logique en SQL brut.
$kernel = new App\Kernel((string) $_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$application = new Application($kernel);
$application->setAutoExit(false);
$application->run(new ArrayInput(['command' => 'app:credit-pack:seed-launch-grid']), new NullOutput());

// Le rate limiter (login throttling, inscription, demande de reset) est adossé à Redis même
// en environnement de test (voir config/packages/cache.yaml) pour que son état survive aux
// reboots du kernel entre deux requêtes d'un même test. Les clés générées par le cache Symfony
// sont hashées (le prefix_seed n'apparaît pas en clair), donc on vide l'instance Redis locale
// de développement au démarrage de la suite plutôt que de tenter un filtrage par motif.
if (isset($_SERVER['REDIS_URL']) && \extension_loaded('redis')) {
    $redisUrl = parse_url((string) $_SERVER['REDIS_URL']);
    if (false !== $redisUrl) {
        $redis = new Redis();
        $redis->connect($redisUrl['host'] ?? '127.0.0.1', $redisUrl['port'] ?? 6379);
        $redis->flushDb();
        $redis->close();
    }
}
