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
        if ($this->ds->count('App\Entity\UserCredential') == 0) {
            foreach ($this->ds->queryMany('App\Entity\User') as $user) {
                $cred = new \App\Entity\UserCredential();
                $cred->created_by = $user->created_by;
                $cred->created_date = $user->created_date;
                $cred->user = $user;
                $cred->driver_id = $user->auth;
                $cred->credential_value = $user->password;
                $this->ds->manage($cred);
                $output->writeln($user->username);
            }
            $this->ds->commit();
        }
        return Command::SUCCESS;
    }
}