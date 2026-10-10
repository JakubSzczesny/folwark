<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CostCategory;
use App\Entity\CostCenter;
use App\Entity\InvoiceRule;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<InvoiceRule>
 */
final class InvoiceRuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('contractorNip', TextType::class, ['label' => 'NIP dostawcy', 'help' => 'Np. dostawca energii, ubezpieczyciel.'])
            ->add('costCenter', EntityType::class, ['label' => 'Ośrodek kosztów', 'class' => CostCenter::class, 'required' => false, 'placeholder' => '—', 'autocomplete' => true])
            ->add('costCategory', EntityType::class, ['label' => 'Kategoria kosztu', 'class' => CostCategory::class, 'required' => false, 'placeholder' => '—', 'autocomplete' => true])
            ->add('note', TextareaType::class, ['label' => 'Opis', 'required' => false])
            ->add('active', CheckboxType::class, ['label' => 'Reguła aktywna', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => InvoiceRule::class]);
    }
}
