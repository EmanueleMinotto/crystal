# -*- mode: ruby -*-
# vi: set ft=ruby :

# Vagrantfile API/syntax version. Don't touch unless you know what you're doing!
VAGRANTFILE_API_VERSION = "2"

Vagrant.configure(VAGRANTFILE_API_VERSION) do |config|
  config.vm.box = "precise64"

  config.vm.network :forwarded_port, guest: 80, host: 81

  # default web server directory
  config.vm.synced_folder ".", "/var/www"

  # developers only have to visit this IP in their browser
  config.vm.network :private_network, ip: "192.168.30.30"

  config.vm.provision "shell", inline: "apt-get install -y apache2 php5 libapache2-mod-php5"
  config.vm.provision "shell", run: "always", inline: "service apache2 reload"
end