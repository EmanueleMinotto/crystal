<?php $this->layout(__DIR__.'/base.html.php'); ?>
        <main>
<?php echo $this->section('content'); ?>
        </main>
<?php $this->start('footer'); ?>Page footer<?php $this->stop(); ?>
