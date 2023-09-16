<?php

declare(strict_types=1);

namespace MultiTester;

class GitHub
{
    /**
     * @var string
     */
    private $repo;

    /**
     * @var string
     */
    private $executor;

    public function __construct(string $repo, $executor = 'shell_exec')
    {
        $this->repo = $repo;
        $this->executor = $executor;
    }

    private function getCurl(string $url): ?string
    {
        $token = getenv('GITHUB_TOKEN');

        return ($this->executor)(
            'curl -s ' .
            '-H "Accept: application/vnd.github.antiope-preview+json" ' .
            (empty($token) ? '' : '-H "Authorization: token ' . $token . '" ') .
            "https://api.github.com/repos/{$this->repo}/commits$url"
        );
    }

    private function getJSON(string $url)
    {
        $response = $this->getCurl($url);

        if (!$response) {
            throw new MultiTesterException(
                "Fetching $url " . (getenv('GITHUB_TOKEN') ? 'with' : 'without') . " GITHUB_TOKEN failed.\n"
            );
        }

        return json_decode($response, true);
    }

    private function isSuccessful(string $sha): bool
    {
        $data = $this->getJSON("/$sha/check-suites");
        $checks = array_filter($data['check_suites'], function ($status) {
            return $status['status'] !== 'queued';
        });

        foreach ($checks as $check) {
            if (!in_array($check['conclusion'], ['neutral', 'success', null], true)) {
                return false;
            }
        }

        return true;
    }

    public function getFirstSuccessfulCommit(?string $branch = null, int $limit = 30): string
    {
        $commits = array_slice($this->getJSON($branch ? "?sha=$branch" : ''), 0, $limit);

        foreach ($commits as ['sha' => $sha]) {
            if ($sha && $this->isSuccessful($sha)) {
                return $sha;
            }
        }

        $message = count($commits) . ' last commits';

        if ($branch) {
            $message .= ' on ' . $branch;
        }

        throw new MultiTesterException(
            "No successful commit found in the $message of {$this->repo}. Output:\n" .
            json_encode($commits, JSON_PRETTY_PRINT)
        );
    }
}
