# Ascender Ledger
Reporting Tool for Ascender

Ascender Ledger is an application that accepts the log stream from Ascender and allows you to easily create reports based upon host fact data and view changes that have occurred during playbook runs.


## Installer
https://github.com/ctrliq/ascender-install

The application consists of 3 roles.  The MySQL Datbase, the Web Server, and the Parser (accepts logs from Controller).


## Trusted Servers
When Ledger first receives data from an Ascender server, it will be added to the Admins > Servers section. All data from untrusted servers will be ignored. You will need to click the lock icon stating that you trust the data from this server before Ledger will accept any incoming logs from it.

Once you have a trusted server, you will start to see both facts and changes populated in the appropriate sections in real time as jobs are ran on Ascender.

You will need to edit the entry for the Server and add its proper URL for accessing Ascender.  This is utilized in the Changes to properly give a link back to the Ascender Job that ran and made the change.

## Facts
Fact data is collected from any module that stores data in ansible_facts. This includes facts from the setup module (gather_facts) but also from an module that automatically registers facts when ran (several of the Windows modules) and even the set_fact module.

We are currently allowing the set_fact module so that you can create custom facts via a playbook, and then utilize it in your reports.  We are working on a whitelist option to allow you to specify which modules are you want to collect facts from, so you can also eliminate unwanted ones.

## Changes
We only record changes that are made on servers. While the non-changes may be important in some ways, only storing the changes helps you to remove a lot of the noise and find the important data about what has happened during your automation.

If your Ascender Job Template has the "Shows Changes" option set, and the module supports it (ex: lineinfile) we will record a diff of what exactly changed.

Searching the changes via the top search box allows you to drill down into the actual data that changed.  So for example, you could search for all changes to happened via automation to your /etc/sudoers files across all your servers.