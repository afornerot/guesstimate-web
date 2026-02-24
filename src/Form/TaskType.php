<?php

namespace App\Form;

use App\Dto\Task;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [];
        foreach ($options['categorys'] as $category) {
            $choices[$category['label']] = $category['id'];
        }
        dump($choices);

        $builder
            ->add('submit', SubmitType::class, [
                'label' => 'Valider',
                'attr' => ['class' => 'btn btn-success no-print me-1'],
            ])
            ->add('label', TextType::class, [
                'label' => 'Libellé',
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Catégorie',
                'choices' => $choices,
            ])
            ->add('optimistic', NumberType::class, [
                'label' => 'Optimiste',
                'scale' => 1,
                'required' => false,
            ])
            ->add('likely', NumberType::class, [
                'label' => 'Probable',
                'scale' => 1,
                'required' => false,
            ])
            ->add('pessimistic', NumberType::class, [
                'label' => 'Pessimiste',
                'scale' => 1,
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Task::class,
            'categorys' => [],
        ]);
    }
}
