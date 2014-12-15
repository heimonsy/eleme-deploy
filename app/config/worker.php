<?php


return array(
    'queue' => array(
        'build' => 'BuildBranchJob',
        'deploy' => 'DeployCommitJob',
        'prbuild' => 'PullRequestBuildJob',
    ),
    'timeout' => array(
        'reserved' => 600,
        'worker' => 300,
    )
);
