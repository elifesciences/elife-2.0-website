elifePipeline {
    stage 'Checkout'
    checkout scm
    def commit = elifeGitRevision()

    stage 'Project tests'
    lock('journal-cms--ci') {
        builderDeployRevision 'journal-cms--ci', commit
        builderProjectTests 'journal-cms--ci', '/srv/journal-cms', ['build/phpunit.xml']
    }

    elifeMainlineOnly {
        stage 'End2end tests'
        elifeEnd2EndTest(
            {
                builderDeployRevision 'journal-cms--end2end', commit
                builderSmokeTests 'journal-cms--end2end', '/srv/journal-cms'
                builderCmd 'journal-cms--end2end', 'cd /srv/journal-cms/web; ../vendor/bin/drush article-import-all --limit=25'
            },
            'two'
        )

        stage 'Approval'
        elifeGitMoveToBranch commit, 'approved'
    }
}
