
            </main>
            
            <!-- Footer -->
            <footer class="border-t border-gray-200/60 bg-white/50 px-4 py-3 lg:px-6">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-gray-400">
                    <p>&copy; <?= date('Y') ?> <?= APP_NAME ?> — Assessoria Empresarial</p>
                    <p>v<?= APP_VERSION ?></p>
                </div>
            </footer>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    
    <!-- Select2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/pt-BR.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- InputMask -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.8/jquery.inputmask.min.js"></script>

    <!-- Custom JS -->
    <script src="<?= APP_URL ?>/assets/js/app.js"></script>

    <?php if (isset($extraScripts)): ?>
        <?= $extraScripts ?>
    <?php endif; ?>
</body>
</html>
