#!/bin/bash
# run as the www-data user. no access to su or sudo
set -ex

# this should be redundent, but just in case
cd /srv/journal-cms/web

# determine if the site is installed. 
# if it looks like it's installed we can skips some steps
rm -f site-was-installed.flag
site_installed=false
if ../vendor/bin/drush cget system.site name; then
    touch site-was-installed.flag
    site_installed=true
fi

if ! $site_installed; then
    ../vendor/bin/drush site-install config_installer -y
    redis-cli -h redis flushall
fi

../vendor/bin/drush updatedb -y
../vendor/bin/drush config-import -y
../vendor/bin/drush cr

if [[ $(../vendor/bin/drush php-eval "print node_access_needs_rebuild()") == "1" ]]; then
    ../vendor/bin/drush php-eval "node_access_rebuild();"
fi

rm -f /tmp/drush-migrate.log
../vendor/bin/drush mi jcms_subjects_json 2>&1 | tee --append /tmp/drush-migrate.log
cat /tmp/drush-migrate.log | ../check-drush-migrate-output.sh

#{% for username, user in pillar.journal_cms.users.items() %}
#journal-cms-defaults-users-{{ username }}:
#    cmd.run:
#        - name: |
#            ../vendor/bin/drush user-create {{ username }} --mail="{{ user.email }}" --password="{{ user.password }}"
#            ../vendor/bin/drush user-add-role "{{ user.role }}" --name={{ username }}
#        - cwd: /srv/journal-cms/web
#        - runas: {{ pillar.elife.deploy_user.username }}
#        - unless:
#            - sudo -u {{ pillar.elife.deploy_user.username}} ../vendor/bin/drush user-information {{ username }}
#        - require:
#            - migrate-content
#{% endfor %}


