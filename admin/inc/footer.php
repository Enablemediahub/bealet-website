    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php $mainJsVersion = @filemtime(__DIR__ . '/../../assets/js/main.js') ?: time(); ?>
    <script>window.ASSET_VERSION = '<?php echo $mainJsVersion; ?>';</script>

    <!-- Main JS -->
    <script src="<?php echo APP_URL; ?>/assets/js/main.js?v=<?php echo $mainJsVersion; ?>"></script>
    
    <script>
        // Confirm delete actions
        function confirmDelete(message = 'Are you sure you want to delete this item?') {
            return confirm(message);
        }
    </script>
</body>
</html>
