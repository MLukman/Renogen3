<?php

namespace App\Command;

use App\Service\DataStore;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateCommand extends Command
{
    protected static $defaultName = 'app:migrate';
    private $ds;

    public function __construct(DataStore $ds)
    {
        $this->ds = $ds;
        parent::__construct();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Populate UserAuthentication
        if ($this->ds->count('App\Entity\UserAuthentication') == 0) {
            $drivers = [];
            foreach ($this->ds->queryMany('App\Entity\User') as $user) {
                if (!$user->auth) {
                    continue;
                }
                $user_auth = new \App\Entity\UserAuthentication($user);
                $user_auth->created_by = $user->created_by;
                $user_auth->created_date = $user->created_date;
                if (!isset($drivers[$user->auth])) {
                    $drivers[$user->auth] = $this->ds->getAuthDriver($user->auth);
                }
                $user_auth->driver = $drivers[$user->auth];
                $user_auth->driver_id = $user->auth;
                $user_auth->credential = $user->password;
                $this->ds->manage($user_auth);
                $output->writeln($user->username);
            }
            $this->ds->commit();
        }

        // Update AuthDriver
        $updateAuthDriverQuery = $this->ds->em()->createQueryBuilder()
            ->update('App\Entity\AuthDriver', 'a')
            ->set('a.class', ':new')
            ->where('a.class = :old')
            ->getQuery();
        $updateAuthDriverQuery->execute([
            'old' => 'Renogen\Auth\Driver\Password',
            'new' => '\App\Security\Authentication\Driver\Password'
        ]);
        $updateAuthDriverQuery->execute([
            'old' => 'App\Auth\Driver\Password',
            'new' => '\App\Security\Authentication\Driver\Password'
        ]);

        // Update Plugin
        $q1 = $this->ds->em()->createQueryBuilder()
            ->update('App\Entity\Plugin', 'p')
            ->set('p.class', ':new')
            ->where('p.class = :old')
            ->getQuery();
        $q1->execute([
            'old' => 'Renogen\Plugin\Taiga\Core',
            'new' => '\App\Plugin\Taiga\Core'
        ]);
        $q1->execute([
            'old' => 'Renogen\Plugin\Telegram\Core',
            'new' => '\App\Plugin\Telegram\Core'
        ]);

        // Update Template
        $q2 = $this->ds->em()->createQueryBuilder()
            ->update('App\Entity\Template', 't')
            ->set('t.class', ':new')
            ->where('t.class = :old')
            ->getQuery();
        $replacements = ['DeployFile', 'Database', 'Instruction', 'PredefinedCommands',
            'PredefinedInstructions'];
        foreach ($replacements as $rep) {
            $q2->execute([
                'old' => "Renogen\\ActivityTemplate\\Impl\\$rep",
                'new' => "\\App\\ActivityTemplate\\Impl\\$rep"
            ]);
        }

        // Update User::$admin
        foreach ($this->ds->queryMany('App\Entity\User') as $user) {
            $user->admin = in_array("ROLE_ADMIN", $user->roles);
        }
        $this->ds->commit();

        return Command::SUCCESS;
    }
}