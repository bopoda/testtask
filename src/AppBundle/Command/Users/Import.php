<?php

namespace AppBundle\Command\Users;

use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Import extends ContainerAwareCommand
{
    const COLUMNS_AMOUNT_IN_CSV = 13;
    const BATCH_ROWS = 1000;
    const DELIMITER = ';';

    private $updatedCnt = 0;
    private $insertedCnt = 0;
    private $processedCnt = 0;
    private $skippedCnt = 0;

    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    private $em;

    protected function configure()
    {
        $this
            ->setName('users:import')
            ->setDescription('Creates new user from CSV file.')
            ->setHelp("This command allows you to import user from csv file.")
            ->addOption(
                'csvFileName',
                'f',
                InputOption::VALUE_REQUIRED,
                'Full path to CSV'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Exception
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $csvFilePath = $input->getOption('csvFileName');

        if (!file_exists($csvFilePath)) {
            throw new \Exception('file not found: '.$csvFilePath);
        }

        $this->processCsvFile($output, $csvFilePath);
    }

    /**
     * @param OutputInterface $output
     * @param $csvFilePath
     * @return void
     */
    private function processCsvFile( OutputInterface $output, $csvFilePath)
    {
        $doctrine = $this->getContainer()->get('doctrine');
        $this->em = $doctrine->getManager();

        $output->writeln(['Start importing (only row with first unique email will be processed)...']);

        $rows = [];
        $existingEmails = [];

        $output->writeln(['Skip first line with titles']);
        if (($handle = fopen($csvFilePath, 'r')) !== false) {
            fgetcsv($handle);

            while (($data = fgetcsv($handle, null, self::DELIMITER)) !== false) {
                $this->processedCnt++;
                if (count($data) != self::COLUMNS_AMOUNT_IN_CSV) {
                    $this->skippedCnt++;
                    continue;
                }

                if (in_array($data[3], $existingEmails)) {
                    $this->skippedCnt++;
                    continue;
                }

                $existingEmails[] = $data[3];
                $rows[] = $data;

                if (count($rows) >= self::BATCH_ROWS) {
                    $this->saveUsersData($rows);
                    $rows = [];
                    $output->writeln([date('Y-m-d H:i:s') . " Processed $this->processedCnt rows, inserted $this->insertedCnt, updated $this->updatedCnt, skipped $this->skippedCnt."]);
                }
            }
            fclose($handle);
        }

        if ($rows) {
            $this->saveUsersData($rows);
        }

        $output->writeln([date('Y-m-d H:i:s') . " Finally, processed $this->processedCnt rows, inserted $this->insertedCnt, updated $this->updatedCnt, skipped $this->skippedCnt."]);
    }

    /**
     * @param array $rows
     * @return void
     */
    private function saveUsersData(array $rows)
    {
        if (!$rows) {
            return;
        }

        $emails = [];
        foreach ($rows as $row) {
            $emails[] = $row[3];
        }

        $query = $this->em->createQueryBuilder()
            ->select('u')
            ->from('AppBundle:User', 'u')
            ->where('u.email in (:emails)')
            ->setParameter('emails', $emails)
            ->getQuery()
        ;

        $existingUsers = $query->getResult();
        $emailToUserMap = [];
        /** @var User $user */
        foreach ($existingUsers as $user) {
            $emailToUserMap[$user->getEmail()] = $user;
        }

        foreach ($rows as $row) {
            if (isset($emailToUserMap[$row[3]])) {
                $user = $emailToUserMap[$row[3]];
                $this->assignValues($user, $row);
                $this->em->persist($user);
                $this->updatedCnt++;
                continue;
            }

            $user = new User();
            $this->assignValues($user, $row);
            $this->em->persist($user);
            $this->insertedCnt++;
        }
        $this->em->flush();
    }

    /**
     * @param User $user
     * @param array $row
     * @return User
     */
    private function assignValues(User $user, array $row)
    {
        $user->setFirstName($row[0]);
        $user->setLastName($row[1]);
        $user->setBirthday(new \DateTime($row[2]));
        $user->setEmail($row[3]);
        $user->setCity($row[4]);
        $user->setZip($row[5]);
        $user->setAddress($row[6]);
        $user->setPhone($row[7]);
        $user->setCompany($row[8]);
        $user->setWorkCity($row[9]);
        $user->setWorkAddress($row[10]);
        $user->setPosition($row[11]);
        $user->setCv($row[12]);
    }
}