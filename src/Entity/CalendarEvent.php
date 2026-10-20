<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CalendarCategory;
use App\Enum\CalendarRecurrence;
use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use App\Repository\CalendarEventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Wydarzenie w kalendarzu gospodarstwa. Bez godziny – całodniowe; z datą końca – wielodniowe.
 * Powtarzane wydarzenie to jeden wpis rozwijany na terminy przy wyświetlaniu (CalendarOccurrences).
 */
#[ORM\Entity(repositoryClass: CalendarEventRepository::class)]
#[ORM\Index(columns: ['farm_id', 'start_date'])]
#[ORM\Index(columns: ['farm_id', 'recurrence'])]
#[ORM\HasLifecycleCallbacks]
class CalendarEvent implements \Stringable, FarmScoped
{
    use BelongsToFarm;

    /** Najdłuższe przypomnienie w dniach (powiadomienie o 6:00). */
    public const int MAX_REMINDER_DAYS = 60;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank(message: 'Podaj tytuł.')]
    #[Assert\Length(max: 160)]
    private string $title = '';

    #[ORM\Column(length: 12, enumType: CalendarCategory::class)]
    private CalendarCategory $category = CalendarCategory::Other;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'Podaj datę.')]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startTime = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endTime = null;

    #[ORM\Column(length: 160, nullable: true)]
    #[Assert\Length(max: 160)]
    private ?string $location = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 10, enumType: CalendarRecurrence::class)]
    private CalendarRecurrence $recurrence = CalendarRecurrence::None;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $recurrenceUntil = null;

    /** Ile dni przed terminem przypomnieć (0 = w dniu wydarzenia, null = bez przypomnienia). */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\Range(min: 0, max: self::MAX_REMINDER_DAYS)]
    private ?int $reminderDays = null;

    /** Prywatne widzi i edytuje tylko autor. */
    #[ORM\Column]
    private bool $private = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'calendar_event_participant')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    #[ORM\OrderBy(['fullName' => 'ASC'])]
    private Collection $participants;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(?User $createdBy = null, ?\DateTimeImmutable $startDate = null)
    {
        $this->createdBy = $createdBy;
        $this->startDate = $startDate?->setTime(0, 0);
        $this->participants = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = trim((string) $title);

        return $this;
    }

    public function getCategory(): CalendarCategory
    {
        return $this->category;
    }

    public function setCategory(CalendarCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate?->setTime(0, 0);

        return $this;
    }

    public function getStartTime(): ?\DateTimeImmutable
    {
        return $this->startTime;
    }

    public function setStartTime(?\DateTimeImmutable $startTime): static
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate?->setTime(0, 0);

        return $this;
    }

    public function getEndTime(): ?\DateTimeImmutable
    {
        return $this->endTime;
    }

    public function setEndTime(?\DateTimeImmutable $endTime): static
    {
        $this->endTime = $endTime;

        return $this;
    }

    public function isAllDay(): bool
    {
        return null === $this->startTime;
    }

    /**
     * Liczba dni, o które wydarzenie wychodzi poza dzień rozpoczęcia (0 = jednodniowe).
     */
    public function getSpanDays(): int
    {
        if (null === $this->startDate || null === $this->endDate || $this->endDate <= $this->startDate) {
            return 0;
        }

        return (int) $this->startDate->diff($this->endDate)->days;
    }

    /**
     * „8:00–10:00”, „od 8:00” albo null dla całodniowego.
     */
    public function getTimeLabel(): ?string
    {
        if (null === $this->startTime) {
            return null;
        }

        $start = $this->startTime->format('G:i');

        return null !== $this->endTime ? $start.'–'.$this->endTime->format('G:i') : $start;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = '' === trim((string) $location) ? null : trim((string) $location);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = '' === trim((string) $description) ? null : $description;

        return $this;
    }

    public function getRecurrence(): CalendarRecurrence
    {
        return $this->recurrence;
    }

    public function setRecurrence(CalendarRecurrence $recurrence): static
    {
        $this->recurrence = $recurrence;

        return $this;
    }

    public function isRecurring(): bool
    {
        return CalendarRecurrence::None !== $this->recurrence;
    }

    public function getRecurrenceUntil(): ?\DateTimeImmutable
    {
        return $this->recurrenceUntil;
    }

    public function setRecurrenceUntil(?\DateTimeImmutable $recurrenceUntil): static
    {
        $this->recurrenceUntil = $recurrenceUntil?->setTime(0, 0);

        return $this;
    }

    public function getReminderDays(): ?int
    {
        return $this->reminderDays;
    }

    public function setReminderDays(?int $reminderDays): static
    {
        $this->reminderDays = $reminderDays;

        return $this;
    }

    public function isPrivate(): bool
    {
        return $this->private;
    }

    public function setPrivate(bool $private): static
    {
        $this->private = $private;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function isCreatedBy(?User $user): bool
    {
        return null !== $user && null !== $this->createdBy && $this->createdBy->getId() === $user->getId();
    }

    /**
     * @return Collection<int, User>
     */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function addParticipant(User $user): static
    {
        if (!$this->participants->contains($user)) {
            $this->participants->add($user);
        }

        return $this;
    }

    public function removeParticipant(User $user): static
    {
        $this->participants->removeElement($user);

        return $this;
    }

    public function isParticipant(?User $user): bool
    {
        return null !== $user && $this->participants->exists(static fn (int $key, User $participant): bool => $participant->getId() === $user->getId());
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Kopia do „Duplikuj”: te same dane, nowy autor, bez identyfikatora.
     */
    public function duplicate(?User $author): self
    {
        $copy = new self($author, $this->startDate);
        $copy->title = $this->title;
        $copy->category = $this->category;
        $copy->startTime = $this->startTime;
        $copy->endDate = $this->endDate;
        $copy->endTime = $this->endTime;
        $copy->location = $this->location;
        $copy->description = $this->description;
        $copy->recurrence = $this->recurrence;
        $copy->recurrenceUntil = $this->recurrenceUntil;
        $copy->reminderDays = $this->reminderDays;
        $copy->private = $this->private;
        foreach ($this->participants as $participant) {
            $copy->addParticipant($participant);
        }

        return $copy;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (null !== $this->startDate && null !== $this->endDate && $this->endDate < $this->startDate) {
            $context->buildViolation('Koniec nie może być przed początkiem.')->atPath('endDate')->addViolation();
        }
        if (null === $this->startTime && null !== $this->endTime) {
            $context->buildViolation('Podaj też godzinę rozpoczęcia.')->atPath('startTime')->addViolation();
        }
        if (null !== $this->startTime && null !== $this->endTime && 0 === $this->getSpanDays() && $this->endTime < $this->startTime) {
            $context->buildViolation('Godzina zakończenia nie może być przed godziną rozpoczęcia.')->atPath('endTime')->addViolation();
        }
        if ($this->isRecurring() && null !== $this->startDate && null !== $this->recurrenceUntil && $this->recurrenceUntil < $this->startDate) {
            $context->buildViolation('Powtarzanie musi kończyć się po pierwszym terminie.')->atPath('recurrenceUntil')->addViolation();
        }
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
