<body>
<p><strong>Message: </strong>Deploy <span style="color: blue;"><?php echo $siteId ?></span> to <span style="color: blue;"><?php echo $hostType ?></span> <span style="color: <?php echo $status == 'Success' ? 'green' : 'red' ?>;"><?php echo $status ?></span></p>
<p><strong>Deploy ID: </strong><?php echo $id ?></p>
<p><strong>Operater: </strong><?php echo $user ?></p>
<p><strong>Status: </strong><span style="color: <?php echo $status == 'Success' ? 'green' : 'red' ?>;"><?php echo $status ?></span></p>
<p><strong>Host Type: </strong><?php echo $hostType ?></p>
<p><strong>Commit: </strong><?php echo $commit ?></p>
<?php if($prevCommit != null && $commit != $prevCommit) {?>
    <p><strong>Diff: </strong><a href="https://github.com/<?php echo $repoName ?>/compare/<?php echo $prevCommit ?>...<?php echo $commit ?>" target="_blank"><?php echo $repoName ?><a></p>
<?php }?>
</body>
