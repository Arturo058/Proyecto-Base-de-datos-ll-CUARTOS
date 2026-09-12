</main>
</div>

<!--  MONITOR GLOBAL DE CONCURRENCIA Y PROPIEDADES ACID PARA EL EXAMEN -->
<?php if (isset($GLOBALS['trazabilidad_acid']) && count($GLOBALS['trazabilidad_acid']) > 0): ?>
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1050; max-width: 450px;">
    <div class="card shadow-lg border-0 bg-white">
        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-2">
            <span class="fw-bold text-dark small" style="font-size: 0.8rem;">🔍 Auditoría del Motor InnoDB (MySQL 8.0)</span>
            <button type="button" class="btn-close" style="font-size: 0.65rem;" onclick="this.closest('.position-fixed').remove()"></button>
        </div>
        <div class="card-body bg-light p-2.5 d-flex flex-column gap-2 font-monospace" style="font-size: 0.75rem;">
            <?php foreach ($GLOBALS['trazabilidad_acid'] as $evento): ?>
                <div class="alert alert-<?= $evento['tipo'] ?> py-1.5 px-3 m-0 shadow-sm border-0 d-flex align-items-center gap-2">
                    <?= htmlspecialchars($evento['msg']) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
