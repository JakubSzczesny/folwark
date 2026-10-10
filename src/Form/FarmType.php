<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Farm;
use App\Util\Nip;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Farm>
 */
final class FarmType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nazwa gospodarstwa',
                'attr' => ['placeholder' => 'np. Gospodarstwo Rolne Kowalscy', 'autofocus' => true],
            ])
            ->add('nip', TextType::class, [
                'label' => 'NIP',
                'required' => false,
                'help' => 'Potrzebny do pobierania faktur z KSeF: faktury, na których gospodarstwo jest nabywcą, to koszty, a sprzedawcą – przychody.',
            ]);

        $builder->get('nip')->addModelTransformer(new CallbackTransformer(
            static fn (?string $nip): ?string => $nip,
            static fn (?string $nip): ?string => null === $nip || '' === trim($nip) ? null : (Nip::normalize($nip) ?? $nip),
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Farm::class]);
    }
}
