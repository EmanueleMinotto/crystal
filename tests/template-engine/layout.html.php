<?php $this->layout(__DIR__.'/layouts/base.html.php'); ?>
<?php $this->start('title'); ?><?php echo $this->e($title); ?><?php $this->stop(); ?>
        <p>Hello <?php echo $this->e($name); ?>!</p>
