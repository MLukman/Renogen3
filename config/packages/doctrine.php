<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container) {
    if (isset($_ENV['DATABASE_MYSQL_SSL_CA_PATH'])) {
        $container->extension('doctrine', [
            'dbal' => [
                'options' => [
                    \PDO::MYSQL_ATTR_SSL_CA => $_ENV['DATABASE_MYSQL_SSL_CA_PATH'],
                ],
            ],
        ]);
    }
};
