# AI Usage Log

## Entry 1

### Task

What I wanted to do.

### Prompt

What I asked the AI.

### Suggested Solution

What the AI suggested.

### My Verification

How I checked the suggestion.

### My Changes

What I changed and why.

# AI Usage Log

## Entry 1

### Task

Set up the Laravel project and configure the local development environment.

### Prompt

I asked the AI how to set up the Laravel project using Laravel Herd, Vue, TypeScript, and MySQL, and how to troubleshoot the setup.

### Suggested Solution

The AI suggested creating the project with the Laravel Vue starter kit, using Laravel Herd for the PHP/Laravel environment, and connecting the project to the local MySQL server provided by XAMPP.

### My Verification

I ran the project, connected it to MySQL, successfully ran the database migrations, and verified that the authentication pages worked.

### My Changes

I created the Laravel project, configured the `.env` database settings, ran the migrations, and verified the initial application setup.

## Entry 2

### Task

Add the required Internship Progress section to the dashboard.

### Prompt

I asked the AI how to add the Internship Progress section using Laravel, Inertia, Vue, and TypeScript.

### Suggested Solution

The AI suggested creating a separate Vue component with typed TypeScript props and passing the Internship Progress data from the Laravel dashboard route through Inertia.

### My Verification

I logged in and confirmed that the Internship Progress section appeared on the dashboard and remained visible after refreshing the page.

### My Changes

I created the `InternshipProgress.vue` component and added the required Internship Progress data to the dashboard route.

## Entry 3

### Task

Replace the hardcoded student name with the authenticated user's name and verify the implementation.

### Prompt

I asked the AI how to use the authenticated user's existing data instead of hardcoding the student name, and how to test the required dashboard behavior.

### Suggested Solution

The AI suggested using the authenticated user already provided through Inertia shared props and passing the user's name to the Internship Progress component. It also suggested adding feature tests for guest access, authenticated dashboard access, and the Internship Progress data.

### My Verification

I logged in with the test account and confirmed that the correct user name appeared on the dashboard. I also ran `php artisan test` and `npm run type-check`. The applicable tests passed and the TypeScript check completed without errors.

### My Changes

I replaced the hardcoded student name with the authenticated user's name, added the dashboard feature test, and corrected an initial Inertia test assertion after it failed.
