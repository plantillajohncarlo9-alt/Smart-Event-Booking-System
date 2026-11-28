# CED EVENT & CATERING SERVICES — Smart Event Booking System

## 1. Project Title

CED EVENT & CATERING SERVICES — Smart Event Booking System

## 2. Description / Overview

This project is a web-based event reservation and management system built with Java, Spring Boot, Thymeleaf, HTML/CSS/JavaScript, and MySQL (`golden_moments_db`) that applies Object-Oriented Programming (OOP) principles. It provides a Home Page (`/`), a server-session-protected customer authentication flow (`/login`, `/register`, `/logout`), an interactive Event Booking Page (`/booking`) where users select event types, venue areas, dates, time slots, guest counts, and packages with duplicate time-slot conflict checking, and an Admin Dashboard (`/admin/dashboard`) for managing booking and payment statuses. Built for **IT-2105** by **Team CED (Team Number 03)** under the tagline *"Plan • Book • Celebrate — Turn your special moments into unforgettable memories."*

## 3. OOP Concepts Applied

### a. Encapsulation

Each class and entity encapsulates its own fields, state, and related behavior.

* `Booking` encapsulates reservation details (`eventType`, `venueArea`, `eventDate`, `timeSlot`, `guests`, `packageType`, `paymentStatus`, `bookingStatus`, `customerName`, `customerEmail`) with private fields and public getters/setters.
* `HomeController.CustomerUser` encapsulates account credentials and profile fields (`fullName`, `username`, `email`, `phone`, `password`) mapped to the `users` table.
* `HomeController` encapsulates HTTP routing, session authentication (`HttpSession`), credential validation, and booking creation logic.

### b. Inheritance

Repository interfaces extend Spring Data JPA's `JpaRepository` to inherit built-in persistence and query capabilities.

* Both `BookingRepository` and `CustomerUserRepository` extend `JpaRepository<T, Long>`.
* They inherit standard database operations (`save`, `findAll`, `findById`, `deleteById`, `existsById`) while adding domain-specific query methods.

### c. Polymorphism

Methods and interfaces behave differently depending on runtime types, request inputs, and HTTP responses.

* `ResponseEntity<?>` allows endpoints like `createBooking` to return polymorphic HTTP responses (`201 Created`, `401 Unauthorized`, or `409 Conflict`) depending on session state and schedule availability.
* Repository and controller methods handle varied lookup and response cases cleanly without separate endpoints for every variation.

### d. Abstraction

Interfaces and framework layers hide underlying SQL queries, connection pooling, and HTTP session management details.

* `BookingRepository` and `CustomerUserRepository` act as repository abstractions defining query contracts while Spring Data JPA generates the implementation.
* `HomeController` and Thymeleaf templates (`index.html`, `booking.html`, `admin.html`, `admin-dashboard.html`) abstract database persistence and server rendering from client interactions.

## 4. Program Structure

### Main Classes and Responsibilities

* `SmartEventSystemApplication` - Entry point of the Spring Boot application (`main` method) that bootstraps the embedded Tomcat server on port `8081` and scans components/repositories.
* `HomeController` - Handles web and API routes (`/`, `/login`, `/register`, `/logout`, `/booking`, `/api/bookings`, `/admin`, `/admin/login`, `/admin/dashboard`, `/admin/bookings/update`, `/admin/bookings/delete`) and manages `HttpSession` state.
* `Booking` - Entity class representing an event reservation record in the database.
* `BookingRepository` (interface) - Extends `JpaRepository<Booking, Long>` with `findAllByOrderByIdDesc()` and `existsByVenueAreaAndEventDateAndTimeSlot(...)`.
* `HomeController.CustomerUser` - Entity class representing a registered customer account in the `users` table.
* `CustomerUserRepository` (interface) - Extends `JpaRepository<CustomerUser, Long>` with `findByEmailIgnoreCaseOrUsernameIgnoreCase(...)`, `existsByEmailIgnoreCase(...)`, and `existsByUsernameIgnoreCase(...)`.

### Class Relationship Diagram

```text
+-----------------------------------+
| SmartEventSystemApplication       |
| (Spring Boot entry point, :8081)  |
+-----------------+-----------------+
                  | boots
                  v
+-----------------------------------+
| HomeController                    |
| (routes, HttpSession, REST API)   |
+-------+-------------------+-------+
        | uses              | uses
        v                   v
+---------------+   +-------------------+
| CustomerUser  |   | Booking           |
| (users table) |   | (bookings table)  |
+-------+-------+   +---------+---------+
        ^                     ^
        | extends             | extends
+-------+---------------+   +-+-------------------------+
| CustomerUserRepository|   | BookingRepository         |
| <<JpaRepository>>     |   | <<JpaRepository>>       |
+-----------------------+   +---------------------------+
