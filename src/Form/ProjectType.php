<?php

namespace App\Form;

use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\CustomerRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjectType extends AbstractType
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            $user = null;
        }

        $builder
        ->add('submit', SubmitType::class, [
            'label' => 'Valider',
            'attr' => ['class' => 'btn btn-success no-print me-1'],
        ])

        ->add('title', TextType::class, [
            'label' => 'Titre',
        ])

        ->add('customer', EntityType::class, [
            'class' => Customer::class,
            'query_builder' => function (CustomerRepository $repository) use ($user) {
                $qb = $repository->createQueryBuilder('c');

                if ($user) {
                    $qb->innerJoin('c.users', 'u')
                        ->where('u.id = :userId')
                        ->setParameter('userId', $user->getId());
                } else {
                    // Si aucun utilisateur n'est connecté, n'afficher aucun client.
                    $qb->where('c.id IS NULL');
                }

                return $qb->orderBy('c.title', 'ASC');
            },
            'choice_label' => 'title',
            'required' => true,
            'label' => 'Client',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => static::class,
            'mode' => 'submit',
        ]);
    }
}
