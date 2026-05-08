<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;

final class FaceRecognitionService
{
    public function __construct(
        private UserRepository $userRepository,
        private float $threshold = 0.50,
    ) {}

    public function findMatchingAdmin(array $probeDescriptor): ?User
    {
        $dimension = count($probeDescriptor);
        
        // Adjust threshold according to descriptor size (128 or 512)
        // Match Java implementation: 0.6 for 128-d, 0.8 for 512-d
        $dynamicThreshold = ($dimension === 128) ? 0.6 : 0.8;

        $qb = $this->userRepository->createQueryBuilder('u')
            ->innerJoin('u.roles', 'r')
            ->andWhere('r.name = :roleAdmin')
            ->andWhere('u.faceDescriptor IS NOT NULL')
            ->setParameter('roleAdmin', 'ROLE_ADMIN');

        /** @var User[] $admins */
        $admins = $qb->getQuery()->getResult();

        if (empty($admins)) {
            return null;
        }

        $bestUser = null;
        $bestDistance = PHP_FLOAT_MAX;

        foreach ($admins as $admin) {
            $stored = $admin->getFaceDescriptor();
            if (!$stored) {
                continue;
            }

            $storedArray = array_map('floatval', explode(',', $stored));

            if (count($storedArray) !== $dimension) {
                continue;
            }

            $distance = $this->euclideanDistance($probeDescriptor, $storedArray);

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestUser = $admin;
            }
        }

        if ($bestUser && $bestDistance <= $dynamicThreshold) {
            return $bestUser;
        }

        return null;
    }

    private function euclideanDistance(array $a, array $b): float
    {
        $sum = 0.0;
        $count = count($a);
        for ($i = 0; $i < $count; $i++) {
            $diff = $a[$i] - $b[$i];
            $sum += $diff * $diff;
        }
        return sqrt($sum);
    }   
}
