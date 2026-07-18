<?php

namespace App\Controller;

use App\Entity\Project;
use App\Form\ProjectType;
use App\Repository\ProjectRepository;
use App\Service\GuesstimateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProjectController extends AbstractController
{
    public function __construct(
        private GuesstimateService $guesstimateService,
        private ProjectRepository $projectRepository,
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('/project', name: 'app_project')]
    public function list(): Response
    {
        $projects = $this->projectRepository->findByCustomerUser($this->getUser());
        $gprojects = [];
        foreach ($projects as $project) {
            $gprojects[$project->getId()]['project'] = $project;
            $gprojects[$project->getId()]['guesstimate'] = $this->guesstimateService->getProject($project);
        }

        return $this->render('project/list.html.twig', [
            'usemenu' => true,
            'usesidebar' => false,
            'title' => 'Liste des Projets',
            'routesubmit' => 'app_project_submit',
            'routeupdate' => 'app_project_view',
            'projects' => $gprojects,
        ]);
    }

    #[Route('/project/submit', name: 'app_project_submit')]
    public function submit(Request $request): Response
    {
        $project = new Project();

        $form = $this->createForm(ProjectType::class, $project, ['mode' => 'submit']);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($project);
            $this->em->flush();
            $this->guesstimateService->addProject($project);

            return $this->redirectToRoute('app_project');
        }

        return $this->render('project/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => false,
            'title' => 'Création Projet',
            'routecancel' => 'app_project',
            'routedelete' => 'app_project_delete',
            'mode' => 'submit',
            'form' => $form,
        ]);
    }

    #[Route('/project/view/{id}', name: 'app_project_view')]
    public function view(int $id): Response
    {
        $project = $this->projectRepository->find($id);
        $this->checkPermission($project);
        $gproject = [];
        $gproject['project'] = $project;
        $gproject['guesstimate'] = $this->guesstimateService->getProject($project);

        return $this->render('project/view.html.twig', [
            'usemenu' => true,
            'usesidebar' => false,
            'title' => 'Projet = '.$project->getTitle(),
            'routesubmit' => 'app_task_submit',
            'routeupdate' => 'app_task_update',
            'routecancel' => 'app_project',
            'project' => $gproject,
        ]);
    }

    #[Route('/project/update/{id}', name: 'app_project_update')]
    public function update(int $id, Request $request): Response
    {
        $project = $this->projectRepository->find($id);
        if (!$project) {
            return $this->redirectToRoute('app_project');
        }

        $form = $this->createForm(ProjectType::class, $project, ['mode' => 'update']);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            return $this->redirectToRoute('app_project');
        }

        return $this->render('project/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => false,
            'title' => 'Modification Projet = '.$project->getTitle(),
            'routecancel' => 'app_project',
            'routedelete' => 'app_project_delete',
            'mode' => 'update',
            'form' => $form,
        ]);
    }

    #[Route('/project/delete/{id}', name: 'app_project_delete')]
    public function delete(int $id): Response
    {
        $project = $this->projectRepository->find($id);
        if (!$project) {
            return $this->redirectToRoute('app_project');
        }

        try {
            $this->em->remove($project);
            $this->em->flush();
        } catch (\Exception $e) {
            $this->addflash('error', $e->getMessage());

            return $this->redirectToRoute('app_project_update', ['id' => $id]);
        }

        return $this->redirectToRoute('app_project');
    }

    #[Route('/project/{id}/import', name: 'app_project_import', methods: ['POST'])]
    public function import(int $id, Request $request): Response
    {
        $project = $this->projectRepository->find($id);
        $this->checkPermission($project);

        /** @var UploadedFile|null $yamlFile */
        $yamlFile = $request->files->get('yaml_file');

        if (!$yamlFile) {
            $this->addflash('error', 'Aucun fichier sélectionné.');

            return $this->redirectToRoute('app_project_view', ['id' => $id]);
        }

        if (!in_array($yamlFile->guessExtension(), ['yml', 'yaml'], true) && !str_ends_with($yamlFile->getClientOriginalName(), '.yml') && !str_ends_with($yamlFile->getClientOriginalName(), '.yaml')) {
            $this->addflash('error', 'Le fichier doit être au format YAML (.yml ou .yaml).');

            return $this->redirectToRoute('app_project_view', ['id' => $id]);
        }

        $yamlContent = file_get_contents($yamlFile->getPathname());
        if (false === $yamlContent) {
            $this->addflash('error', 'Impossible de lire le fichier.');

            return $this->redirectToRoute('app_project_view', ['id' => $id]);
        }

        try {
            $this->guesstimateService->importProjectYaml($project, $yamlContent);
            $this->addflash('success', 'Le fichier YAML a été importé avec succès.');
        } catch (\Exception $e) {
            $this->addflash('error', 'Erreur lors de l\'import : '.$e->getMessage());
        }

        return $this->redirectToRoute('app_project_view', ['id' => $id]);
    }

    private function checkPermission(Project $project): void
    {
        if (!$project) {
            throw $this->createNotFoundException('Ce project n\'existe pas');
        }

        if (!$project->getCustomer()->getUsers()->contains($this->getUser())) {
            throw $this->createAccessDeniedException('Accès refusé : vous n\'avez pas les droits.');
        }
    }
}
