<?php

declare(strict_types=1);

namespace App\Billing\Form;

use App\Billing\Enum\CreditPackBadge;
use App\Billing\Request\CreditPackRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<CreditPackRequest>
 */
final class CreditPackFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('credits', IntegerType::class, ['label' => 'Credits'])
            ->add('priceCents', IntegerType::class, ['label' => 'Price (cents)'])
            ->add('currency', TextType::class, ['label' => 'Currency (ISO 4217, e.g. usd)'])
            ->add('badge', EnumType::class, [
                'label' => 'Badge',
                'class' => CreditPackBadge::class,
                'required' => false,
                'placeholder' => 'None',
            ])
            ->add('displayOrder', IntegerType::class, ['label' => 'Display order'])
            ->add('active', CheckboxType::class, ['label' => 'Active', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CreditPackRequest::class,
        ]);
    }
}
