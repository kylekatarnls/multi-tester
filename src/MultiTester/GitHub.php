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

    private function getCurl(string $url): string
    {
        $token = getenv('GITHUB_TOKEN');
        $completeUrl = "https://api.github.com/repos/{$this->repo}/commits$url";

        $response = ($this->executor)(
            'curl -s ' .
            '-H "Accept: application/vnd.github.antiope-preview+json" ' .
            (empty($token) ? '' : '-H "Authorization: token ' . $token . '" ') .
            $completeUrl
        );

        if (!$response) {
            throw new MultiTesterException(
                "Fetching $completeUrl " .
                (getenv('GITHUB_TOKEN') ? 'with' : 'without') . ' GITHUB_TOKEN failed.'
            );
        }

        return $response;
    }

    private function getJSON(string $url)
    {
        return json_decode($this->getCurl($url), true);
    }

    /**
     * @throws MultiTesterException
     */
    private function getJSONArray(string $url, ?string $key = null): array
    {
        $data = $this->getJSON($url);
        $list = $key === null ? $data : ($data[$key] ?? null);

        if (!is_array($list) || ($key === null && isset($data['message']))) {
            throw new MultiTesterException(
                "Unexpected response from https://api.github.com/repos/{$this->repo}/commits$url" .
                (is_array($data) && isset($data['message']) ? ': ' . $data['message'] : '.')
            );
        }

        return $list;
    }

    /**
     * @throws MultiTesterException
     */
    private function isSuccessful(string $sha): bool
    {
        $checks = array_filter($this->getJSONArray("/$sha/check-suites", 'check_suites'), function ($status) {
            return $status['status'] !== 'queued';
        });

        foreach ($checks as $check) {
            if (!in_array($check['conclusion'], ['neutral', 'success', null], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws MultiTesterException
     */
    public function getFirstSuccessfulCommit(?string $branch = null, int $limit = 30): string
    {
        $commits = array_slice($this->getJSONArray($branch ? "?sha=$branch" : ''), 0, $limit);

        foreach ($commits as $commit) {
            $sha = $commit['sha'] ?? null;

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
