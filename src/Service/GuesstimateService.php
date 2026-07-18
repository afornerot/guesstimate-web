<?php

namespace App\Service;

use App\Dto\Category;
use App\Dto\Task;
use App\Entity\Customer;
use App\Entity\Project;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class GuesstimateService
{
    private string $workingDir;
    private string $guesstimatePath;

    public function __construct(KernelInterface $kernel)
    {
        $this->guesstimatePath = 'guesstimate';
        $this->workingDir = $kernel->getProjectDir().'/uploads/guesstimate';
    }

    public function initConfig(Customer $customer): string
    {
        $filesystem = new Filesystem();
        if (!$filesystem->exists($this->workingDir.'/config')) {
            $filesystem->mkdir($this->workingDir.'/config');
        }

        return $this->execute([
            'config', 'init',
            '-c', $this->workingDir.'/config/'.$customer->getId().'.yml',
            '--force',
        ]);
    }

    public function getConfig(Customer $customer): array
    {
        return json_decode($this->execute([
            'config', 'view',
            '-c', $this->workingDir.'/config/'.$customer->getId().'.yml',
            '--format', 'json',
        ]), true);
    }

    public function getCategorys(Customer $customer): array
    {
        $configs = $this->getConfig($customer);
        $categorys = [];
        foreach ($configs['TaskCategories'] as $category) {
            $categorys[$category['ID']]['id'] = $category['ID'];
            $categorys[$category['ID']]['label'] = $category['Label'];
            $categorys[$category['ID']]['costPerTimeUnit'] = $category['CostPerTimeUnit'];
        }

        return $categorys;
    }

    public function findCategory(Customer $customer, string $category): bool
    {
        foreach ($customer->getProjects() as $project) {
            $gproject = $this->getProject($project);
            foreach ($gproject['tasks'] as $gtask) {
                if ($gtask['category'] == $category) {
                    return true;
                }
            }
        }

        return false;
    }

    public function addCategory(Customer $customer, Category $category): string
    {
        return $this->execute([
            'config', 'category', 'add',
            $category->getId(),
            $category->getLabel(),
            '--cost', $category->getCostPerTimeUnit(),
            '-c', $this->workingDir.'/config/'.$customer->getId().'.yml',
        ]);
    }

    public function removeCategory(Customer $customer, string $category): string
    {
        return $this->execute([
            'config', 'category', 'remove',
            $category,
            '-c', $this->workingDir.'/config/'.$customer->getId().'.yml',
        ]);
    }

    public function getProject(Project $project): array
    {
        return json_decode($this->execute([
            'view', $this->workingDir.'/project/'.$project->getId().'.yml',
            '-c',  $this->workingDir.'/config/'.$project->getCustomer()->getId().'.yml',
            '--format', 'json',
        ]), true);
    }

    public function addProject(Project $project): string
    {
        $filesystem = new Filesystem();
        if (!$filesystem->exists($this->workingDir.'/prpject')) {
            $filesystem->mkdir($this->workingDir.'/project');
        }

        return $this->execute([
            'new',
            $project->getTitle(),
            '-o', $this->workingDir.'/project/'.$project->getId().'.yml',
            '-c',  $this->workingDir.'/config/'.$project->getCustomer()->getId().'.yml',
        ]);
    }

    public function getTask(Project $project, string $id): Task
    {
        $task = new Task();
        $gproject = $this->getProject($project);
        foreach ($gproject['tasks'] as $gtask) {
            if ($gtask['id'] == $id) {
                $task->setId($gtask['id']);
                $task->setLabel($gtask['label']);
                $task->setCategory($gtask['category']);
                $task->setOptimistic($gtask['estimations']['optimistic']);
                $task->setLikely($gtask['estimations']['likely']);
                $task->setPessimistic($gtask['estimations']['pessimistic']);
                $task->setProject($project);

                break;
            }
        }

        return $task;
    }

    public function addTask(Task $task): string
    {
        $args = [
            'task', 'add',
            $this->workingDir.'/project/'.$task->getProject()->getId().'.yml',
            $task->getLabel(),
            '--category', $task->getCategory(),
        ];

        if (null !== $task->getOptimistic()) {
            $args[] = '-o';
            $args[] = $task->getOptimistic();
        }
        if (null !== $task->getLikely()) {
            $args[] = '--likely';
            $args[] = $task->getLikely();
        }
        if (null !== $task->getPessimistic()) {
            $args[] = '-p';
            $args[] = $task->getPessimistic();
        }

        return $this->execute($args);
    }

    public function updateTask(Task $task): string
    {
        $args = [
            'task', 'update',
            $this->workingDir.'/project/'.$task->getProject()->getId().'.yml',
            $task->getId(),
            '--label', $task->getLabel(),
            '--category', $task->getCategory(),
        ];

        if (null !== $task->getOptimistic()) {
            $args[] = '-o';
            $args[] = $task->getOptimistic();
        }
        if (null !== $task->getLikely()) {
            $args[] = '--likely';
            $args[] = $task->getLikely();
        }
        if (null !== $task->getPessimistic()) {
            $args[] = '-p';
            $args[] = $task->getPessimistic();
        }

        return $this->execute($args);
    }

    public function removeTask(Task $task): string
    {
        $args = [
            'task', 'remove',
            $this->workingDir.'/project/'.$task->getProject()->getId().'.yml',
            $task->getId(),
        ];

        return $this->execute($args);
    }

    public function moveTask(Project $project, string $taskId, int $position): string
    {
        return $this->execute([
            'task', 'move',
            $this->workingDir.'/project/'.$project->getId().'.yml',
            $taskId,
            (string) $position,
        ]);
    }

    public function reorderTask(Project $project, array $taskIds): string
    {
        return $this->execute(array_merge([
            'task', 'reorder',
            $this->workingDir.'/project/'.$project->getId().'.yml',
        ], $taskIds));
    }

    public function importProjectYaml(Project $project, string $yamlContent): void
    {
        $data = Yaml::parse($yamlContent);
        if (!is_array($data) || !isset($data['tasks'])) {
            throw new \InvalidArgumentException('Le fichier YAML ne contient pas de données de projet valides (clé "tasks" manquante).');
        }

        $this->ensureCategoriesExist($project->getCustomer(), $data['tasks']);

        $filesystem = new Filesystem();
        $projectFile = $this->workingDir.'/project/'.$project->getId().'.yml';
        $filesystem->dumpFile($projectFile, $yamlContent);
    }

    public function ensureCategoriesExist(Customer $customer, array $yamlTasks): void
    {
        $config = $this->getConfig($customer);
        $existingCategoryIds = array_keys($config['TaskCategories'] ?? []);

        $yamlCategoryIds = array_unique(array_column($yamlTasks, 'category'));

        foreach ($yamlCategoryIds as $categoryId) {
            if (!in_array($categoryId, $existingCategoryIds)) {
                $category = new Category();
                $category->setId($categoryId);
                $category->setLabel(ucfirst(str_replace('-', ' ', $categoryId)));
                $category->setCostPerTimeUnit(500);
                $this->addCategory($customer, $category);
            }
        }
    }

    private function execute(array $args): string
    {
        $process = new Process(array_merge([$this->guesstimatePath], $args), $this->workingDir);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }
}
