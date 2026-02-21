<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\Club;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['email'], message: 'This email is already used by another account.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'Email is required.')]
    #[Assert\Email(message: 'Please enter a valid email address.')]
    private ?string $email = null;

    /**
     * The hashed password - validation is done on plainPassword in the form
     */
    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'First name is required.')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'First name must be at least {{ limit }} characters.',
        maxMessage: 'First name cannot exceed {{ limit }} characters.'
    )]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Last name is required.')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Last name must be at least {{ limit }} characters.',
        maxMessage: 'Last name cannot exceed {{ limit }} characters.'
    )]
    private ?string $lastName = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20, maxMessage: 'Phone number cannot exceed {{ limit }} characters.')]
    private ?string $phone = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500, maxMessage: 'Address cannot exceed {{ limit }} characters.')]
    private ?string $address = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Status is required.')]
    #[Assert\Choice(
        choices: ['PENDING', 'ACTIVE', 'INACTIVE'],
        message: 'Status must be PENDING, ACTIVE, or INACTIVE.'
    )]
    private ?string $status = 'PENDING';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastLoginAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $emailVerifiedAt = null;

    /**
     * RELATION ManyToMany: A User can have many Roles, and a Role can belong to many Users.
     * Example: User "Ali" can be both MEMBER and LIBRARIAN.
     */
    #[ORM\ManyToMany(targetEntity: Role::class, inversedBy: 'users')]
    #[ORM\JoinTable(name: 'user_role')]
    private Collection $roles;

    /**
     * RELATION OneToOne: Each User has exactly one ReadingProfile.
     * cascade persist = when we save a User, the ReadingProfile is saved too.
     * cascade remove = when we delete a User, the ReadingProfile is deleted too.
     */
    #[ORM\OneToOne(mappedBy: 'user', targetEntity: ReadingProfile::class, cascade: ['persist', 'remove'])]
    private ?ReadingProfile $readingProfile = null;

    #[ORM\ManyToMany(targetEntity: Club::class, mappedBy: 'members')]
    private Collection $clubs;

    /** @var Collection<int, Community> */
    #[ORM\ManyToMany(targetEntity: Community::class, mappedBy: 'members')]
    private Collection $communities;

    /** @var Collection<int, Community> */
    #[ORM\OneToMany(mappedBy: 'createdBy', targetEntity: Community::class)]
    private Collection $createdCommunities;

    /** @var Collection<int, Post> */
    #[ORM\OneToMany(mappedBy: 'createdBy', targetEntity: Post::class)]
    private Collection $createdPosts;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
        $this->clubs = new ArrayCollection();
        $this->communities = new ArrayCollection();
        $this->createdCommunities = new ArrayCollection();
        $this->createdPosts = new ArrayCollection();

        $this->createdAt = new \DateTime();
        $this->status = 'PENDING';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    /**
     * A visual identifier that represents this user.
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeInterface
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeInterface $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }

    public function getEmailVerifiedAt(): ?\DateTimeInterface
    {
        return $this->emailVerifiedAt;
    }

    public function setEmailVerifiedAt(?\DateTimeInterface $emailVerifiedAt): static
    {
        $this->emailVerifiedAt = $emailVerifiedAt;
        return $this;
    }

    /**
     * @return Collection<int, Role>
     */
    public function getUserRoles(): Collection
    {
        return $this->roles;
    }

    /**
     * Set roles from a collection (used by forms)
     */
    public function setUserRoles(Collection $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    /**
     * @see UserInterface
     * Returns the roles granted to the user as an array of strings for Symfony Security.
     */
    public function getRoles(): array
    {
        $roleNames = [];
        foreach ($this->roles as $role) {
            $roleNames[] = $role->getName();
        }
        // Guarantee every user at least has ROLE_USER
        $roleNames[] = 'ROLE_USER';
        return array_unique($roleNames);
    }

    public function addRole(Role $role): static
    {
        if (!$this->roles->contains($role)) {
            $this->roles->add($role);
        }
        return $this;
    }

    public function removeRole(Role $role): static
    {
        $this->roles->removeElement($role);
        return $this;
    }

    public function getReadingProfile(): ?ReadingProfile
    {
        return $this->readingProfile;
    }

    public function setReadingProfile(?ReadingProfile $readingProfile): static
    {
        if ($readingProfile !== null && $readingProfile->getUser() !== $this) {
            $readingProfile->setUser($this);
        }
        $this->readingProfile = $readingProfile;
        return $this;
    }

    /**
     * Returns "FirstName LastName" - handy for displaying the user's name.
     */
    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    /**
     * Check if the user has a specific role by name.
     */
    public function hasRole(string $roleName): bool
    {
        foreach ($this->roles as $role) {
            if ($role->getName() === $roleName) {
                return true;
            }
        }
        return false;
    }

    /**
     * When Symfony needs to display this object as text (e.g. in a dropdown),
     * it will show "FirstName LastName".
     */
    public function __toString(): string
    {
        return $this->getFullName();
    }

    /** @return Collection<int, Club> */
    public function getClubs(): Collection
    {
        return $this->clubs;
    }

    public function addClub(Club $club): static
    {
        if (!$this->clubs->contains($club)) {
            $this->clubs->add($club);
            $club->addMember($this);
        }

        return $this;
    }

    public function removeClub(Club $club): static
    {
        if ($this->clubs->removeElement($club)) {
            $club->removeMember($this);
        }

        return $this;
    }

    /** @return Collection<int, Community> */
    public function getCommunities(): Collection
    {
        return $this->communities;
    }

    public function addCommunity(Community $community): static
    {
        if (!$this->communities->contains($community)) {
            $this->communities->add($community);
            $community->addMember($this);
        }

        return $this;
    }

    public function removeCommunity(Community $community): static
    {
        if ($this->communities->removeElement($community)) {
            $community->removeMember($this);
        }

        return $this;
    }

    /** @return Collection<int, Community> */
    public function getCreatedCommunities(): Collection
    {
        return $this->createdCommunities;
    }

    /** @return Collection<int, Post> */
    public function getCreatedPosts(): Collection
    {
        return $this->createdPosts;
    }
}
