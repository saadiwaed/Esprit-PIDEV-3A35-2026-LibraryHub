<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;

/**
 * Service de reconnaissance faciale pour les administrateurs.
 *
 * - Stockage des descripteurs dans User::faceDescriptor (chaîne "v1,v2,...").
 * - Recherche de l'admin le plus proche selon une distance euclidienne.
 */
final class FaceRecognitionService
{
    public function __construct(
        private UserRepository $userRepository,
        private float $threshold = 0.43,
    ) {
    }

    public function getThreshold(): float
    {
        return $this->threshold;
    }

    /**
     * @param float[] $probeDescriptor Descripteur calculé côté navigateur (face-api.js).
     */
    public function findMatchingAdmin(array $probeDescriptor): ?User
    {
        // Récupère les utilisateurs qui ont ROLE_ADMIN et un descripteur enregistré
        $qb = $this->userRepository->createQueryBuilder('u')
            ->innerJoin('u.roles', 'r')
            ->andWhere('r.name = :roleAdmin')
            ->andWhere('u.faceDescriptor IS NOT NULL')
            ->setParameter('roleAdmin', 'ROLE_ADMIN');

        /** @var User[] $admins */
        $admins = $qb->getQuery()->getResult();

        if (!$admins) {
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
            if (count($storedArray) !== count($probeDescriptor)) {
                continue;
            }

            $distance = $this->euclideanDistance($probeDescriptor, $storedArray);

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestUser = $admin;
            }
        }

        if ($bestUser && $bestDistance <= $this->threshold) {
            return $bestUser;
        }

        return null;
    }

    /**
     * Calcule la distance euclidienne entre deux descripteurs.
     *
     * @param float[] $a
     * @param float[] $b
     */
    private function euclideanDistance(array $a, array $b): float
    {
        $diffs = array_map(
            static fn (float $x, float $y): float => ($x - $y) ** 2,
            $a,
            $b,
        );

        return sqrt(array_sum($diffs));
    }
}

