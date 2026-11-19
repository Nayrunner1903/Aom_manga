
pipeline {
    agent {
        label 'windows-uat'
    }

    options {
        timestamps()
        disableConcurrentBuilds()
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Build Docker Image') {
            steps {
                bat 'docker build -t manga-laravel:build-%BUILD_NUMBER% .'
            }
        }

        stage('Deploy Blue-Green') {
            steps {
                bat 'powershell.exe -NoProfile -ExecutionPolicy Bypass -File deploy\\deploy-uat.ps1'
            }
        }
    }

    post {
        success {
            echo 'UAT deployment completed successfully.'
        }

        failure {
            echo 'UAT deployment failed. Check deployment log and rollback status.'
        }
    }
}
