    </main><!-- /.main-content -->
</div><!-- /.page-wrapper -->

<!-- Site Footer -->
<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-brand">
            <strong>East Renfrewshire <span class="gold">Council</span></strong>
            <span>Housing Digital Transformation</span>
            <span style="margin-top:0.5rem; color: rgba(255,255,255,0.5); font-size:0.75rem;">Interview Research Portal</span>
        </div>

        <div class="footer-meta">
            <p><strong>Prepared for:</strong> East Renfrewshire Council Interview</p>
            <p><strong>Post applied for:</strong> Housing Digital Transformation Lead</p>
            <p><strong>Me:</strong> William Ellis</p>
            <p style="margin-top:0.5rem; color: rgba(255,255,255,0.5);">Interview Date: 17/03/2026 11:30 &ndash; 12:30</p>
        </div>

        <div class="footer-disclaimer">
            <p>This research portal has been prepared by William Ellis for interview purposes only. All information is drawn from publicly available sources including East Renfrewshire Council publications, Scottish Government strategy documents, and sector research.</p>
            <p style="margin-top:0.5rem;">Content is accurate to the best of the author's knowledge at the time of preparation and is intended to demonstrate research depth and professional preparation for the role.</p>
        </div>
    </div>

    <div class="footer-bottom">
        &copy; <?php echo date('Y'); ?> William Ellis &mdash; ERC Housing Digital Transformation Interview Research &mdash; All Rights Reserved
    </div>
</footer>

<?php
$scriptPath = ltrim($_SERVER['PHP_SELF'], '/');
$depth = substr_count($scriptPath, '/');
$assetBase = $depth > 1 ? str_repeat('../', $depth - 1) : '';
?>
<script src="<?php echo $assetBase; ?>assets/js/main.js"></script>
</body>
</html>
