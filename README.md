# Ascender Ledger
Reporting Tool for Ascender

The idea behind this application is to accept the log stream from Ascender and easily create reports based upon host fact data and view changes that have occurred during playbook runs.


## Installer
https://github.com/ctrliq/ascender-install

The application consists of 4 roles.  The MySQL Datbase, the Web Server, the Parser (accepts logs from Controller), and a Load Balancer.  

All these roles can reside on the same server or can reside on separate servers.  If using a single instance or containers, the LB is not necessary.  If using the LB, all the instances can be setup in an HA configuration.  Database HA is currently not setup by the installation script (Work in Progress).

## Installation
If installed using the above installer, it will install both Ascender and Ledger into containers on a K3s cluster.

It is still possible to install onto VMs using the old installer at
https://github.com/cigamit/ansible-ledger-install
but you will need to modify the installer roles to use the new repository
