<?php

namespace App\Controller;

use App\Dto\Task;
use App\Entity\Project;
use App\Form\TaskType;
use App\Repository\ProjectRepository;
use App\Service\GuesstimateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TaskController extends AbstractController
{
    public function __construct(
        private GuesstimateService $guesstimateService,
        private ProjectRepository $projectRepository,
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('/task/{idProject}/submit', name: 'app_task_submit')]
    public function submit(int $idProject, Request $request): Response
    {
        $project = $this->projectRepository->find($idProject);
        $this->checkPermission($project);

        $categorys = $this->guesstimateService->getCategorys($project->getCustomer());

        $task = new Task();
        $task->setProject($project);

        $form = $this->createForm(TaskType::class, $task, ['categorys' => $categorys]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->guesstimateService->addTask($task);

            return $this->redirectToRoute('app_project_view', ['id' => $idProject]);
        }

        return $this->render('task/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => false,
            'title' => 'Création Tâche',
            'routecancel' => 'app_project_view',
            'routedelete' => 'app_task_delete',
            'mode' => 'submit',
            'form' => $form,
            'task' => $task,
        ]);
    }

    #[Route('/task/{idProject}/update/{id}', name: 'app_task_update')]
    public function update(int $idProject, string $id, Request $request): Response
    {
        $project = $this->projectRepository->find($idProject);
        $this->checkPermission($project);

        $categorys = $this->guesstimateService->getCategorys($project->getCustomer());

        $task = $this->guesstimateService->getTask($project, $id);
        $form = $this->createForm(TaskType::class, $task, ['categorys' => $categorys]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->guesstimateService->updateTask($task);

            return $this->redirectToRoute('app_project_view', ['id' => $idProject]);
        }

        return $this->render('task/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => false,
            'title' => 'Modification Tâche = '.$task->getLabel(),
            'routecancel' => 'app_project_view',
            'routedelete' => 'app_task_delete',
            'mode' => 'update',
            'form' => $form,
            'task' => $task,
        ]);
    }

    #[Route('/task/{idProject}/delete/{id}', name: 'app_task_delete')]
    public function delete(int $idProject, string $id): Response
    {
        $project = $this->projectRepository->find($idProject);
        $this->checkPermission($project);

        $task = $this->guesstimateService->getTask($project, $id);
        try {
            $this->guesstimateService->removeTask($task);
        } catch (\Exception $e) {
            $this->addflash('error', $e->getMessage());

            return $this->redirectToRoute('app_task_update', ['id' => $id]);
        }

        return $this->redirectToRoute('app_project_view', ['id' => $idProject]);
    }

    #[Route('/task/{idProject}/reorder', name: 'app_task_reorder', methods: ['POST'])]
    public function move(int $idProject, Request $request): JsonResponse
    {
        $project = $this->projectRepository->find($idProject);
        $this->checkPermission($project);

        $taskIds = $request->request->all()['taskIds'] ?? null;

        if (!$taskIds) {
            return new JsonResponse(['status' => 'error', 'message' => 'Missing parameters'], Response::HTTP_BAD_REQUEST);
        }

        $this->guesstimateService->reorderTask($project, $taskIds);

        return new JsonResponse(['status' => 'success']);
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
