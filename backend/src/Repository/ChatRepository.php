<?php

namespace App\Repository;

use App\Entity\ChatMessage;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChatMessage>
 */
class ChatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatMessage::class);
    }

    /**
     * Get the full conversation between two users, newest last.
     *
     * @return ChatMessage[]
     */
    public function findConversation(User $a, User $b, int $limit = 50): array
    {
        return $this->createQueryBuilder('m')
            ->where(
                '(m.sender = :a AND m.receiver = :b) OR (m.sender = :b AND m.receiver = :a)'
            )
            ->setParameter('a', $a)
            ->setParameter('b', $b)
            ->orderBy('m.sentAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get messages in a conversation sent after a given message ID (for polling).
     *
     * @return ChatMessage[]
     */
    public function findAfter(User $a, User $b, int $afterId): array
    {
        return $this->createQueryBuilder('m')
            ->where(
                '(m.sender = :a AND m.receiver = :b) OR (m.sender = :b AND m.receiver = :a)'
            )
            ->andWhere('m.id > :afterId')
            ->setParameter('a', $a)
            ->setParameter('b', $b)
            ->setParameter('afterId', $afterId)
            ->orderBy('m.sentAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get the list of distinct students who have sent messages to the given supervisor user.
     * Returns the latest message per student conversation.
     *
     * @return array<int, array{student: User, lastMessage: ChatMessage, unread: int}>
     */
    public function findStudentConversations(User $supervisorUser): array
    {
        // Get distinct senders who messaged the supervisor
        $senders = $this->createQueryBuilder('m')
            ->select('DISTINCT IDENTITY(m.sender) AS senderId')
            ->where('m.receiver = :sup')
            ->setParameter('sup', $supervisorUser)
            ->getQuery()
            ->getScalarResult();

        // Also get students the supervisor messaged (to show those conversations too)
        $receivers = $this->createQueryBuilder('m')
            ->select('DISTINCT IDENTITY(m.receiver) AS receiverId')
            ->where('m.sender = :sup')
            ->setParameter('sup', $supervisorUser)
            ->getQuery()
            ->getScalarResult();

        $userIds = array_unique(array_merge(
            array_column($senders, 'senderId'),
            array_column($receivers, 'receiverId')
        ));

        if (empty($userIds)) {
            return [];
        }

        $em = $this->getEntityManager();
        $result = [];

        foreach ($userIds as $userId) {
            $partner = $em->getRepository(User::class)->find($userId);
            if (!$partner) {
                continue;
            }

            // Get latest message in this conversation
            $last = $this->createQueryBuilder('m')
                ->where(
                    '(m.sender = :sup AND m.receiver = :p) OR (m.sender = :p AND m.receiver = :sup)'
                )
                ->setParameter('sup', $supervisorUser)
                ->setParameter('p', $partner)
                ->orderBy('m.sentAt', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            $unread = (int) $this->createQueryBuilder('m')
                ->select('COUNT(m.id)')
                ->where('m.sender = :p AND m.receiver = :sup AND m.isRead = false')
                ->setParameter('p', $partner)
                ->setParameter('sup', $supervisorUser)
                ->getQuery()
                ->getSingleScalarResult();

            $result[] = [
                'student'     => $partner,
                'lastMessage' => $last,
                'unread'      => $unread,
            ];
        }

        // Sort by latest message descending
        usort($result, fn ($a, $b) =>
            $b['lastMessage']?->getSentAt() <=> $a['lastMessage']?->getSentAt()
        );

        return $result;
    }
}
