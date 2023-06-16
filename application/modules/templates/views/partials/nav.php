<nav class="navbar navbar-expand-xl w-100">
    <ul class="navbar-nav justify-content-start flex-grow-1 gap-1">



        <li class="nav-item dropdown">
            <a href="<?php echo base_url()?>dashboard" class="nav-link <?php echo activelink('dashboard', $this->uri->segment(1)) ?>">
                <div class="parent-icon"><i class="bx bx-category"></i>
                </div>
                <div class="menu-title">Staff Dashboard</div>
            </a>
        </li>


        <li class="nav-item dropdown">
            <a href="<?php echo base_url()?>staff" class="nav-link <?php echo activelink('staff', $this->uri->segment(1)) ?>">
                <div class="parent-icon"><i class='bx bx-user'></i>
                </div>
                <div class="menu-title">Staff</div>
            </a>

        </li>

        <li class="nav-item dropdown">
            <a href="due.php" class="nav-link <?php echo activelink('due', $this->uri->segment(1)) ?>">
                <div class="parent-icon"><i class="bx bx-category"></i>
                </div>
                <div class="menu-title">Contracts Due</div>
            </a>
        </li>


        <li class="nav-item dropdown">
            <a href="expired.php" class="nav-link <?php echo activelink('expired', $this->uri->segment(1)) ?>">
                <div class="parent-icon"><i class='bx bx-user-x'></i>
                </div>
                <div class="menu-title">Expired Contracts</div>
            </a>

        </li>

        <li class="nav-item dropdown">
            <a href="staff_report.php" class="nav-link <?php echo activelink('staff_report', $this->uri->segment(1)) ?>">
                <div class="parent-icon"><i class="bx bx-list-check"></i>
                </div>
                <div class="menu-title">Staff Report</div>
            </a>
        </li>


        <li class="nav-item dropdown">
            <a href="staff_bd.php" class="nav-link <?php echo activelink('staff_bd', $this->uri->segment(1)) ?>">
                <div class="parent-icon"><i class='bx bx-donate-heart'></i>
                </div>
                <div class="menu-title">Staff Birthdays</div>
            </a>

        </li>

        <li class="nav-item dropdown">
            <a href="former_staff.php" class="nav-link <?php echo activelink('former_staff', $this->uri->segment(1)) ?>">
                <div class="parent-icon"><i class='bx bx-user-minus'></i>
                </div>
                <div class="menu-title">Former Staff</div>
            </a>

        </li>
        <li class="nav-item dropdown">
            <a href="system_settings.php" class="nav-link <?php echo activelink('system_settings', $this->uri->segment(1)) ?>">
                <div class="parent-icon"><i class="bx bx-line-chart"></i>
                </div>
                <div class="menu-title">Settings</div>
            </a>

        </li>
        <li class="nav-item dropdown">
            <a href="#" class="nav-link <?php echo activelink('change_password', $this->uri->segment(1)) ?>">
                <div class="parent-icon"><i class='bx bx-bookmark-heart'></i>
                </div>
                <div class="menu-title">Change Password</div>
            </a>

        </li>
    </ul>



</nav>