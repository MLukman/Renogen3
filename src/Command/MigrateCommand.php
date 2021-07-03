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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($this->ds->count('App\Entity\UserAuthentication') == 0) {
            foreach ($this->ds->queryMany('App\Entity\User') as $user) {
                if (!$user->auth) {
                    continue;
                }
                $user_auth = new \App\Entity\UserAuthentication($user);
                $user_auth->created_by = $user->created_by;
                $user_auth->created_date = $user->created_date;
                $user_auth->driver_id = $user->auth;
                $user_auth->credential = $user->password;
                $this->ds->manage($user_auth);
                $output->writeln($user->username);
            }
            $this->ds->commit();
        }
        return Command::SUCCESS;
    }
}