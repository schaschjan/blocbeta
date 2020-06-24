<?php

namespace App\Entity;

use App\Components\Entity\TimestampableInterface;
use App\Components\Entity\TimestampTrait;
use App\Components\Entity\LocationResourceInterface;
use App\Components\Entity\LocationTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(indexes={@ORM\Index(name="user", columns={"user_id"})})
 * @ORM\HasLifecycleCallbacks()
 */
class Ascent implements LocationResourceInterface, TimestampableInterface
{
    public const ASCENT_TOP = 'top';
    public const ASCENT_FLASH = 'flash';
    public const ASCENT_RESIGNED = 'resignation';

    use TimestampTrait;
    use LocationTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @var Boulder
     * @ORM\ManyToOne(targetEntity="Boulder", inversedBy="ascents")
     * @ORM\JoinColumn(name="boulder_id", referencedColumnName="id")
     */
    private $boulder;

    /**
     * @var User
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="ascents")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    private $user;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=false)
     */
    private $type;

    /**
     * @var int
     * @ORM\Column(type="integer", nullable=true)
     */
    private $score;

    /**
     * @var string
     * @ORM\Column(type="string", unique=true)
     */
    private $checksum;

    /**
     * @var int
     * @ORM\Column(name="user_id", type="integer")
     */
    private $userId;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id)
    {
        $this->id = $id;
    }

    /**
     * @return Boulder
     */
    public function getBoulder()
    {
        return $this->boulder;
    }

    /**
     * @param Boulder $boulder
     */
    public function setBoulder(Boulder $boulder)
    {
        $this->boulder = $boulder;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType(string $type)
    {
        $this->type = $type;
    }

    public function isType(string $type): bool
    {
        return $type === $this->getType();
    }

    public function setDoubted()
    {
        $this->setType($this->getType() . '-pending-doubt');
    }

    public function isDoubted(): bool
    {
        return strpos($this->getType(), '-pending-doubt') !== false;
    }

    public function getScore()
    {
        return $this->score;
    }

    public function setScore(int $score)
    {
        $this->score = round($score);
    }

    public function getUser()
    {
        return $this->user;
    }

    public function setUser(User $user)
    {
        $this->user = $user;
    }

    public function getChecksum()
    {
        return $this->checksum;
    }

    /**
     * @ORM\PrePersist()
     */
    public function setChecksum()
    {
        $this->checksum = md5($this->boulder->getId() . $this->user->getId());
    }
}
