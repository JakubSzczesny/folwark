<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Contractor;
use App\Util\Nip;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Contractor>
 */
final class ContractorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nazwa'])
            ->add('shortName', TextType::class, ['label' => 'Nazwa skrócona', 'required' => false, 'help' => 'Wyświetlana na listach, np. „Petromex”.']);

        if ($options['allow_nip']) {
            $builder->add('nip', TextType::class, ['label' => 'NIP', 'required' => false, 'help' => 'Kontrahenci z NIP tworzą się sami przy fakturach z KSeF.']);
            $builder->get('nip')->addModelTransformer(new CallbackTransformer(
                static fn (?string $nip): ?string => $nip,
                static fn (?string $nip): ?string => null === $nip || '' === trim($nip) ? null : (Nip::normalize($nip) ?? $nip),
            ));
        }

        $builder
            ->add('street', TextType::class, ['label' => 'Ulica i numer', 'required' => false])
            ->add('postalCode', TextType::class, ['label' => 'Kod pocztowy', 'required' => false])
            ->add('city', TextType::class, ['label' => 'Miejscowość', 'required' => false])
            ->add('country', TextType::class, ['label' => 'Kraj (kod)', 'attr' => ['maxlength' => 2]])
            ->add('email', EmailType::class, ['label' => 'E-mail', 'required' => false])
            ->add('phone', TelType::class, ['label' => 'Telefon', 'required' => false])
            ->add('contactPerson', TextType::class, ['label' => 'Osoba kontaktowa', 'required' => false])
            ->add('deliveryDays', IntegerType::class, ['label' => 'Czas dostawy (dni)', 'required' => false, 'help' => 'Uwzględniany przy przypomnieniach o zamówieniach.'])
            ->add('active', CheckboxType::class, ['label' => 'Aktywny', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Contractor::class,
            'allow_nip' => false,
        ]);
    }
}
