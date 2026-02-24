<?php

namespace App\Controller;

use App\Dto\Category;
use App\Entity\Customer;
use App\Form\CustomerType;
use App\Repository\CustomerRepository;
use App\Service\GuesstimateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CustomerController extends AbstractController
{
    public function __construct(
        private GuesstimateService $guesstimateService,
        private CustomerRepository $customerRepository,
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('/admin/customer', name: 'app_admin_customer')]
    public function list(): Response
    {
        $customers = $this->customerRepository->findAll();

        return $this->render('customer/list.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Liste des Clients',
            'routesubmit' => 'app_admin_customer_submit',
            'routeupdate' => 'app_admin_customer_update',
            'customers' => $customers,
        ]);
    }

    #[Route('/admin/customer/submit', name: 'app_admin_customer_submit')]
    public function submit(Request $request): Response
    {
        $customer = new Customer();

        $form = $this->createForm(CustomerType::class, $customer, ['mode' => 'submit']);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($customer);
            $this->em->flush();
            $this->guesstimateService->initConfig($customer);

            return $this->redirectToRoute('app_admin_customer');
        }

        return $this->render('customer/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Création Client',
            'routecancel' => 'app_admin_customer',
            'routedelete' => 'app_admin_customer_delete',
            'mode' => 'submit',
            'form' => $form,
        ]);
    }

    #[Route('/admin/customer/update/{id}', name: 'app_admin_customer_update')]
    public function update(int $id, Request $request): Response
    {
        $customer = $this->customerRepository->find($id);
        if (!$customer) {
            return $this->redirectToRoute('app_admin_customer');
        }

        $gcategorys = $this->guesstimateService->getCategorys($customer);
        $gcatids = [];
        foreach ($gcategorys as $gcatogory) {
            $catogory = new Category();
            $catogory->setId($gcatogory['id']);
            $catogory->setLabel($gcatogory['label']);
            $catogory->setCostPerTimeUnit($gcatogory['costPerTimeUnit']);
            $customer->addCategory($catogory);
            $gcatids[] = $gcatogory['id'];
        }

        $form = $this->createForm(CustomerType::class, $customer, ['mode' => 'update']);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $categorys = $form->getData()->getCategorys();
            $catids = [];
            foreach ($categorys as $category) {
                $catids[] = $category->getId();

                if (in_array($category->getId(), $gcatids)) {
                    $this->guesstimateService->removeCategory($customer, $category->getId());
                }
                $this->guesstimateService->addCategory($customer, $category);
            }

            $flashed = false;
            foreach ($gcatids as $gcatid) {
                if (!in_array($gcatid, $catids)) {
                    // S'assurer avant suppression que la categorie n'est pas utilisé par un projet
                    if (!$this->guesstimateService->findCategory($customer, $gcatid)) {
                        $this->guesstimateService->removeCategory($customer, $gcatid);
                    } elseif (!$flashed) {
                        $flashed = true;
                        $this->addFlash('info', 'Impossible de supprimer certaines catégories car elles sont liées à un projet.');
                    }
                }
            }
            $this->em->flush();

            return $this->redirectToRoute('app_admin_customer');
        }

        return $this->render('customer/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Modification Client = '.$customer->getTitle(),
            'routecancel' => 'app_admin_customer',
            'routedelete' => 'app_admin_customer_delete',
            'mode' => 'update',
            'form' => $form,
        ]);
    }

    #[Route('/admin/customer/delete/{id}', name: 'app_admin_customer_delete')]
    public function delete(int $id): Response
    {
        $customer = $this->customerRepository->find($id);
        if (!$customer) {
            return $this->redirectToRoute('app_admin_customer');
        }

        try {
            $this->em->remove($customer);
            $this->em->flush();
        } catch (\Exception $e) {
            $this->addflash('error', $e->getMessage());

            return $this->redirectToRoute('app_admin_customer_update', ['id' => $id]);
        }

        return $this->redirectToRoute('app_admin_customer');
    }
}
