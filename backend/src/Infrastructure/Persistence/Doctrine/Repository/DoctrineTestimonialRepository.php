<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\Testimonial;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineTestimonialRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findPublished(): array
    {
        return $this->em->createQuery(
            'SELECT t FROM App\Domain\Entity\Testimonial t WHERE t.published = true ORDER BY t.createdAt DESC'
        )->getResult();
    }

    public function findAll(): array
    {
        return $this->em->createQuery(
            'SELECT t FROM App\Domain\Entity\Testimonial t ORDER BY t.createdAt DESC'
        )->getResult();
    }

    public function find(int $id): ?Testimonial
    {
        return $this->em->find(Testimonial::class, $id);
    }

    public function save(Testimonial $t): void
    {
        $this->em->persist($t);
        $this->em->flush();
    }

    public function delete(Testimonial $t): void
    {
        $this->em->remove($t);
        $this->em->flush();
    }
}
