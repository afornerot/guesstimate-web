<?php

namespace App\Form;

use App\Dto\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'Titre',
                'required' => true,
                'row_attr' => ['class' => 'align-horizontal mb-3'],
            ])
            ->add('costPerTimeUnit', NumberType::class, [
                'label' => 'Coût',
                'required' => true,
                'row_attr' => ['class' => 'align-horizontal mb-3'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Category::class,
        ]);
    }
}
